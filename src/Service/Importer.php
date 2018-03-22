<?php
/**
 * Created by PhpStorm.
 * User: gordon
 * Date: 22/3/2561
 * Time: 21:09 น.
 */
namespace Axllent\WeblogWPImport\Service;

use Axllent\WeblogWPImport\Lib\WPXMLParser;
use SilverStripe\Blog\Model\BlogCategory;
use SilverStripe\Blog\Model\BlogPost;
use SilverStripe\Blog\Model\BlogTag;
use SilverStripe\Comments\Model\Comment;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;

class Importer
{
    /** @var array converted xml for categories, posts */
    private $import;

    public function __construct($blog)
    {
        $this->blog = $blog;
    }

    public function getImportData($xml)
    {

        $parser = new WPXMLParser($xml);

        $data = $parser->XML2Data();
        if (!$data) {
            return false;
        }

        $categories_lookup = [];
        $categories = ArrayList::create();
        foreach ($data->Posts as $post) {
            foreach ($post->Categories as $cat) { //}$url => $title) {
                if (!isset($categories_lookup[$cat->URLSegment])) {
                    $categories_lookup[$cat->URLSegment] = $cat->Title;
                    $categories->push(ArrayData::create([
                        'URLSegment' => $cat->URLSegment,
                        'Title' => $cat->Title
                    ]));
                }
            }
        }

        $this->import = ArrayData::create([
            'SiteURL' => $data->SiteURL,
            'Posts' => $data->Posts->filter('Status', 'publish'),
            'Categories' => $categories
        ]);


        return $this->import;
    }


    /**
     * @param $blog
     * @param $status
     * @return array
     */
    public function processCategories()
    {
        $categories_created = 0;
        /* Check all categories exist */
        foreach ($this->import->Categories as $category) {
            $cat = $this->blog->Categories()->filter('Title', $category->Title)->first();
            if (!$cat) {
                $cat = BlogCategory::create([
                    'Title' => $category->Title
                ]);
                error_log('Added category ' . $category->Title);
                $this->blog->Categories()->add($cat);
                $categories_created++;
            }
        }
    }

    public function processTags()
    {
        error_log('---- tags ----');
        error_log(print_r($this->import->Tags, 1));

        foreach ($this->import->Tags as $tag) {
            $tagForPost = $this->blog->Tags()->filter('Title', $tag->Title)->first();
            if (!$tagForPost) {
                $tagForPost = BlogTag::create([
                    'Title' => $tag->Title
                ]);
                error_log('Added tag ' . $tagForPost->Title);
                $this->blog->Tags()->add($tagForPost);
            }
        }
    }


    function processPosts()
    {
        foreach ($this->import->Posts as $orig) {
            $blog_post = BlogPost::get()->filter('URLSegment', $orig->URLSegment)->first();

            if (!$blog_post) {
                $blog_post = BlogPost::create([
                    'URLSegment' => $orig->URLSegment
                ]);
            }
            $blog_post->Title = $orig->Title;
            $blog_post->PublishDate = $orig->PublishDate;
            $blog_post->ParentID = $this->blog->ID;
            $blog_post->HasBrokenLink = 0;
            $blog_post->HasBrokenFile = 0;

            // Now we parse the hell out of the content
            $content = $orig->Content;

            // Format WordPress code
            $content = $this->wpautop($content);

            $blog_post->Content = $content;

            $blog_post->write();
            $blog_post->doPublish();

            $comments = $orig->Comments;
            foreach($comments as $commentData) {
                $comment = new Comment();
                $comment->Name = $commentData->Name;
                $comment->URL = $commentData->URL;
                $comment->Content = $commentData->Content;
                $comment->ParentID = $blog_post->ID;
          //      $comment->write();

                error_log('... saved comment from ' . $comment->Email);
            }

            /*
             * [WordPressID] => 2046
            [Email] => lamar_weingarth@gmail.com
            [Name] => Super real
            [URL] => http://www.kwonloospice38.com
            [Created] => 2014-06-05 20:05:27
            [LastEdited] => 2014-06-05 20:05:27
            [Content] => After learning about the Vel' d'Hiv, Julia finds herself questioning the life
that they has led with her selfish husband Super real if you want to have
such loan within the range of lucrative loan quotes, it really is greatly essential for you to search on the internet before
you're gonna make an application for it.
        )

             */
        }
    }


    /**
     * @param $data
     * @param $overwrite
     * @param $blog_posts_added
     * @param $blog_posts_updated
     * @param $blog
     * @param $import_filters
     * @param $remove_styles_and_classes
     * @param $assets_downloaded
     * @param $set_image_width
     * @param $urlsegment_link_rewrite
     * @param $matches
     * @param $remove_shortcodes
     * @param $scrape_for_featured_images
     * @param $process_categories
     * @return array
     * @throws \SilverStripe\ORM\ValidationException
     */
    /*
    public function importPosts($data, $blog, $import_filters,
                                $remove_styles_and_classes, $assets_downloaded, $set_image_width,
                                $urlsegment_link_rewrite, $matches, $remove_shortcodes,
                                $process_categories)
    {
        foreach ($this->import->Posts as $orig) {
            $blog_post = BlogPost::get()->filter('URLSegment', $orig->URLSegment)->first();

            if (!$blog_post) {
                $blog_post = BlogPost::create([
                    'URLSegment' => $orig->URLSegment
                ]);
                $blog_posts_added++;
            } else {
                $blog_posts_updated++;
            }

            $blog_post->Title = $orig->Title;
            $blog_post->PublishDate = $orig->PublishDate;
            $blog_post->ParentID = $blog->ID;
            $blog_post->HasBrokenLink = 0;
            $blog_post->HasBrokenFile = 0;

            // Now we parse the hell out of the content
            $content = $orig->Content;

            // Format WordPress code
            $content = $this->wpautop($content);

            if ($import_filters) {
                foreach ($import_filters as $fcn) {
                    $html_fcn = 'html_' . $fcn;
                    if (ClassInfo::hasMethod($this, $html_fcn)) {
                        $content = $this->$html_fcn($content, $data);
                    }
                }
            }

            $dom = \SimpleHtmlDom\str_get_html(
                $content,
                $lowercase = true,
                $forceTagsClosed = true,
                $target_charset = 'UTF-8',
                $stripRN = false
            );

            if ($dom) {
                if ($remove_styles_and_classes) {
                    // remove all styles
                    foreach ($dom->find('*[style]') as $el) {
                        $el->style = false;
                    }
                    // remove all classes except for images
                    foreach ($dom->find('*[class]') as $el) {
                        if ($el->tag != 'img') {
                            $el->class = false;
                        }
                    }
                }


                foreach ($dom->find('img') as $img) {
                    if ($class = $img->class) {
                        if (preg_match('/\bplaceholder\b/', $class)) {
                            // ignore - media
                        } elseif (preg_match('/\balignright\b/', $class)) {
                            $img->class = 'right ss-htmleditorfield-file image';
                        } elseif (preg_match('/\balignleft\b/', $class)) {
                            $img->class = 'left ss-htmleditorfield-file image';
                        } elseif (preg_match('/\baligncenter\b/', $class)) {
                            $img->class = 'center ss-htmleditorfield-file image';
                        } else {
                            $img->class = 'leftAlone ss-htmleditorfield-file image';
                        }
                    } else {
                        $img->class = false;
                    }

                    $orig_src = $img->src;
                    if (!$orig_src) {
                        continue;
                    }

                    $parts = parse_url($orig_src);
                    if (empty($parts['path'])) {
                        continue;
                    }

                    if (!preg_match('/^' . preg_quote($import->SiteURL, '/') . '/', $orig_src)) {
                        continue; // don't download remote images - too problematic re: filenames
                    }

                    $orig_src = rtrim($import->SiteURL, '/') . $parts['path'];

                    $non_scaled = preg_replace('/^(.*)(\-\d\d\d?\d?x\d\d\d?\d?)\.([a-z]{3,4})$/', '${1}.${3}', $orig_src);

                    $file_name = @pathinfo($non_scaled, PATHINFO_BASENAME);
                    $nameFilter = FileNameFilter::create();
                    $file_name = $nameFilter->filter($file_name);

                    if (!$file_name) {
                        $blog_post->HasBrokenFile = 1;
                        continue;
                    }

                    $file = Image::get()->filter('FileFilename', $this->featured_image_folder . '/' . $file_name)->first();
                    if (!$file) {
                        // Download asset
                        $data = $this->getRemoteFile($non_scaled);

                        if (!$data) {
                            if ($non_scaled != $orig_src) {
                                // Try download the image directly (maybe scaling params are in the original filename?)
                                $data = $this->getRemoteFile($orig_src);
                            }
                            if (!$data) {
                                // Create a a broken image
                                $new_tag = '[image src="' . $orig_src . '" id="0"';
                                if ($v = $img->width) {
                                    $new_tag .= ' width="' . $v . '"';
                                }
                                if ($v = $img->height) {
                                    $new_tag .= ' height="' . $v . '"';
                                }
                                $new_tag .= ' class="' . $img->class . '"';
                                $new_tag .= ' alt="' . $img->alt . '"';
                                if ($v = $img->title) {
                                    $new_tag .= ' title="' . $img->title . '"';
                                }
                                $new_tag .= ']';
                                $img->outertext = $new_tag;
                                $blog_post->HasBrokenFile = 1;
                                continue; // 404
                            }
                        }

                        $assets_downloaded++;

                        $file = new Image();
                        $file->setFromString($data, $this->featured_image_folder . '/' . $file_name);
                        if ($img->title) {
                            // $file->Name = $file_name;
                            $file->Title = $img->title;
                        } elseif ($img->alt) {
                            // $file->Name = $file_name;
                            $file->Title = $img->alt;
                        }
                        $file->write();
                        $file->doPublish();
                    }

                    if ($file) {
                        // Manually create shortcode
                        $img_width = $img->width ? $img->width : false;
                        $img_height = $img->height ? $img->height : false;

                        // Rescale if set & image is large enough and options set
                        $src_width = $file->getWidth();
                        $src_height = $file->getHeight();
                        if ($set_image_width && $file->getWidth() >= $set_image_width) {
                            $ratio = $src_width / $src_height;
                            $img_width = $set_image_width;
                            $img_height = round($set_image_width / $ratio);
                        }

                        $src = $file->Link();
                        $new_tag = '[image src="' . $src . '" id="' . $file->ID . '"';
                        if ($img_width) {
                            $new_tag .= ' width="' . $img_width . '"';
                        }
                        if ($img_height) {
                            $new_tag .= ' height="' . $img_height . '"';
                        }
                        $new_tag .= ' class="' . $img->class . '"';
                        $new_tag .= ' alt="' . $img->alt . '"';
                        if ($v = $img->title) {
                            $new_tag .= ' title="' . $img->title . '"';
                        }
                        $new_tag .= ']';
                        $img->outertext = $new_tag;
                    }
                }


                foreach ($dom->find('a[href^=' . $import->SiteURL . ']') as $a) {
                    if ($href = $a->href) {
                        $parts = parse_url($href);

                        $link_file = @pathinfo($parts['path'], PATHINFO_BASENAME);

                        if ($link_file == '') { // home link
                            $link_file = 'home';
                        }

                        $a->href = '[sitetree_link,id=0]';
                        $a->class = 'ss-broken';

                        if (!empty($urlsegment_link_rewrite[$link_file])) {
                            $sitetree_urlsegment = $urlsegment_link_rewrite[$link_file];
                        } else {
                            $sitetree_urlsegment = $link_file;
                        }
                        $page = SiteTree::get()->filter('URLSegment', $sitetree_urlsegment)->first();

                        if ($page) {
                            $a->href = '[sitetree_link,id=' . $page->ID . ']';
                            $a->class = false;
                            $a->target = false;
                            $a->title = false;
                            continue;
                        }

                        $nameFilter = FileNameFilter::create();
                        $file_name = $nameFilter->filter($link_file);

                        $has_ext = preg_match('/\.([a-z0-9]{3,4})$/i', $file_name, $matches);

                        if (!$has_ext) {
                            $blog_post->HasBrokenLink = 1;
                            continue; // No extension - not a File
                        }

                        $ext = strtolower($matches[1]);

                        $file = File::get()->filter('Name', $file_name)->first();

                        if (!$file) {
                            $data = $this->getRemoteFile($href);

                            if (!$data) { // 404
                                $a->href = '[file_link,id=0]';
                                $a->class = 'ss-broken';
                                $blog_post->HasBrokenFile = 1;
                                continue;
                            }

                            $assets_downloaded++;

                            if (in_array($ext, ['gif', 'jpeg', 'jpg', 'png', 'bmp'])) {
                                $file = new Image();
                            } else {
                                $file = new File();
                            }

                            $file->setFromString($data, $this->featured_image_folder . '/' . $file_name);
                            $file->write();
                            $file->doPublish();
                        }
                        if ($file) {
                            // re-link to file
                            $a->href = '[file_link,id=' . $file->ID . ']';
                            $a->class = false;
                        }
                    }
                }

                $content = trim($dom->save());
            }


            if ($remove_shortcodes) {
                $content = $this->html_remove_shortcodes($content);
            }

            $blog_post->Content = $content;

            $blog_post->write();
            $blog_post->doPublish();

            // Add categories
            if ($process_categories) {
                $categories = $orig->Categories;
                foreach ($categories as $category) {
                    if (!$blog_post->Categories()->filter('Title', $category->Title)->first()) {
                        $cat_obj = $blog_post->Parent()->Categories()->filter('Title', $category->Title)->first();
                        if ($cat_obj->exists()) {
                            $blog_post->Categories()->add($cat_obj);
                        }
                    }
                }
            }
        }
        return array($blog_posts_added, $blog_posts_updated, $assets_downloaded);
    }
    */



    /**
     * Replaces double line-breaks with paragraph elements.
     *
     * A group of regex replaces used to identify text formatted with newlines and
     * replace double line-breaks with HTML paragraph tags. The remaining line-breaks
     * after conversion become <<br />> tags, unless $br is set to '0' or 'false'.
     *
     * @since 0.71
     *
     * @param string $pee The text which has to be formatted.
     * @param bool   $br  Optional. If set, this will convert all remaining line-breaks
     *                    after paragraphing. Default true.
     * @return string Text which has been converted into correct paragraph tags.
     */
    public function wpautop($pee, $br = true)
    {
        $pre_tags = array();

        if (trim($pee) === '') {
            return '';
        }

        // Just to make things a little easier, pad the end.
        $pee = $pee . "\n";

        /*
         * Pre tags shouldn't be touched by autop.
         * Replace pre tags with placeholders and bring them back after autop.
         */
        if (strpos($pee, '<pre') !== false) {
            $pee_parts = explode('</pre>', $pee);
            $last_pee = array_pop($pee_parts);
            $pee = '';
            $i = 0;

            foreach ($pee_parts as $pee_part) {
                $start = strpos($pee_part, '<pre');

                // Malformed html?
                if ($start === false) {
                    $pee .= $pee_part;
                    continue;
                }

                $name = "<pre wp-pre-tag-$i></pre>";
                $pre_tags[$name] = substr($pee_part, $start) . '</pre>';

                $pee .= substr($pee_part, 0, $start) . $name;
                $i++;
            }

            $pee .= $last_pee;
        }
        // Change multiple <br>s into two line breaks, which will turn into paragraphs.
        $pee = preg_replace('|<br\s*/?>\s*<br\s*/?>|', "\n\n", $pee);

        $allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';

        // Add a double line break above block-level opening tags.
        $pee = preg_replace('!(<' . $allblocks . '[\s/>])!', "\n\n$1", $pee);

        // Add a double line break below block-level closing tags.
        $pee = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $pee);

        // Standardize newline characters to "\n".
        $pee = str_replace(array("\r\n", "\r"), "\n", $pee);

        // Find newlines in all elements and add placeholders.
        $pee = $this->wp_replace_in_html_tags($pee, array( "\n" => ' <!-- wpnl --> ' ));

        // Collapse line breaks before and after <option> elements so they don't get autop'd.
        if (strpos($pee, '<option') !== false) {
            $pee = preg_replace('|\s*<option|', '<option', $pee);
            $pee = preg_replace('|</option>\s*|', '</option>', $pee);
        }

        /*
         * Collapse line breaks inside <object> elements, before <param> and <embed> elements
         * so they don't get autop'd.
         */
        if (strpos($pee, '</object>') !== false) {
            $pee = preg_replace('|(<object[^>]*>)\s*|', '$1', $pee);
            $pee = preg_replace('|\s*</object>|', '</object>', $pee);
            $pee = preg_replace('%\s*(</?(?:param|embed)[^>]*>)\s*%', '$1', $pee);
        }

        /*
         * Collapse line breaks inside <audio> and <video> elements,
         * before and after <source> and <track> elements.
         */
        if (strpos($pee, '<source') !== false || strpos($pee, '<track') !== false) {
            $pee = preg_replace('%([<\[](?:audio|video)[^>\]]*[>\]])\s*%', '$1', $pee);
            $pee = preg_replace('%\s*([<\[]/(?:audio|video)[>\]])%', '$1', $pee);
            $pee = preg_replace('%\s*(<(?:source|track)[^>]*>)\s*%', '$1', $pee);
        }

        // Collapse line breaks before and after <figcaption> elements.
        if (strpos($pee, '<figcaption') !== false) {
            $pee = preg_replace('|\s*(<figcaption[^>]*>)|', '$1', $pee);
            $pee = preg_replace('|</figcaption>\s*|', '</figcaption>', $pee);
        }

        // Remove more than two contiguous line breaks.
        $pee = preg_replace("/\n\n+/", "\n\n", $pee);

        // Split up the contents into an array of strings, separated by double line breaks.
        $pees = preg_split('/\n\s*\n/', $pee, -1, PREG_SPLIT_NO_EMPTY);

        // Reset $pee prior to rebuilding.
        $pee = '';

        // Rebuild the content as a string, wrapping every bit with a <p>.
        foreach ($pees as $tinkle) {
            $pee .= '<p>' . trim($tinkle, "\n") . "</p>\n";
        }

        // Under certain strange conditions it could create a P of entirely whitespace.
        $pee = preg_replace('|<p>\s*</p>|', '', $pee);

        // Add a closing <p> inside <div>, <address>, or <form> tag if missing.
        $pee = preg_replace('!<p>([^<]+)</(div|address|form)>!', '<p>$1</p></$2>', $pee);

        // If an opening or closing block element tag is wrapped in a <p>, unwrap it.
        $pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', '$1', $pee);

        // In some cases <li> may get wrapped in <p>, fix them.
        $pee = preg_replace('|<p>(<li.+?)</p>|', '$1', $pee);

        // If a <blockquote> is wrapped with a <p>, move it inside the <blockquote>.
        $pee = preg_replace('|<p><blockquote([^>]*)>|i', '<blockquote$1><p>', $pee);
        $pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);

        // If an opening or closing block element tag is preceded by an opening <p> tag, remove it.
        $pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)!', '$1', $pee);

        // If an opening or closing block element tag is followed by a closing <p> tag, remove it.
        $pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*</p>!', '$1', $pee);

        // Optionally insert line breaks.
        if ($br) {
            // Replace newlines that shouldn't be touched with a placeholder.
            $pee = preg_replace_callback('/<(script|style).*?<\/\\1>/s', array($this, '_autop_newline_preservation_helper'), $pee);

            // Normalize <br>
            $pee = str_replace(array( '<br>', '<br/>' ), '<br />', $pee);

            // Replace any new line characters that aren't preceded by a <br /> with a <br />.
            $pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee);

            // Replace newline placeholders with newlines.
            $pee = str_replace('<WPPreserveNewline />', "\n", $pee);
        }

        // If a <br /> tag is after an opening or closing block tag, remove it.
        $pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', '$1', $pee);

        // If a <br /> tag is before a subset of opening or closing block tags, remove it.
        $pee = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $pee);
        $pee = preg_replace("|\n</p>$|", '</p>', $pee);

        // Replace placeholder <pre> tags with their original content.
        if (!empty($pre_tags)) {
            $pee = str_replace(array_keys($pre_tags), array_values($pre_tags), $pee);
        }

        // Restore newlines in all elements.
        if (false !== strpos($pee, '<!-- wpnl -->')) {
            $pee = str_replace(array( ' <!-- wpnl --> ', '<!-- wpnl -->' ), "\n", $pee);
        }

        return $pee;
    }

    /**
     * Replace characters or phrases within HTML elements only.
     *
     * @since 4.2.3
     *
     * @param string $haystack The text which has to be formatted.
     * @param array $replace_pairs In the form array('from' => 'to', ...).
     * @return string The formatted text.
     */
    public function wp_replace_in_html_tags($haystack, $replace_pairs)
    {
        // Find all elements.
        $textarr = $this->wp_html_split($haystack);
        $changed = false;

        // Optimize when searching for one item.
        if (1 === count($replace_pairs)) {
            // Extract $needle and $replace.
            foreach ($replace_pairs as $needle => $replace);

            // Loop through delimiters (elements) only.
            for ($i = 1, $c = count($textarr); $i < $c; $i += 2) {
                if (false !== strpos($textarr[$i], $needle)) {
                    $textarr[$i] = str_replace($needle, $replace, $textarr[$i]);
                    $changed = true;
                }
            }
        } else {
            // Extract all $needles.
            $needles = array_keys($replace_pairs);

            // Loop through delimiters (elements) only.
            for ($i = 1, $c = count($textarr); $i < $c; $i += 2) {
                foreach ($needles as $needle) {
                    if (false !== strpos($textarr[$i], $needle)) {
                        $textarr[$i] = strtr($textarr[$i], $replace_pairs);
                        $changed = true;
                        // After one strtr() break out of the foreach loop and look at next element.
                        break;
                    }
                }
            }
        }

        if ($changed) {
            $haystack = implode($textarr);
        }

        return $haystack;
    }

    /**
     * Separate HTML elements and comments from the text.
     *
     * @since 4.2.4
     *
     * @param string $input The text which has to be formatted.
     * @return array The formatted text.
     */
    public function wp_html_split($input)
    {
        return preg_split($this->get_html_split_regex(), $input, -1, PREG_SPLIT_DELIM_CAPTURE);
    }

    /**
     * Retrieve the regular expression for an HTML element.
     *
     * @since 4.4.0
     *
     * @return string The regular expression
     */
    public function get_html_split_regex()
    {
        static $regex;

        if (! isset($regex)) {
            $comments =
                '!'           // Start of comment, after the <.
                . '(?:'         // Unroll the loop: Consume everything until --> is found.
                .     '-(?!->)' // Dash not followed by end of comment.
                .     '[^\-]*+' // Consume non-dashes.
                . ')*+'         // Loop possessively.
                . '(?:-->)?';   // End of comment. If not found, match all input.

            $cdata =
                '!\[CDATA\['  // Start of comment, after the <.
                . '[^\]]*+'     // Consume non-].
                . '(?:'         // Unroll the loop: Consume everything until ]]> is found.
                .     '](?!]>)' // One ] not followed by end of comment.
                .     '[^\]]*+' // Consume non-].
                . ')*+'         // Loop possessively.
                . '(?:]]>)?';   // End of comment. If not found, match all input.

            $escaped =
                '(?='           // Is the element escaped?
                .    '!--'
                . '|'
                .    '!\[CDATA\['
                . ')'
                . '(?(?=!-)'      // If yes, which type?
                .     $comments
                . '|'
                .     $cdata
                . ')';

            $regex =
                '/('              // Capture the entire match.
                .     '<'           // Find start of element.
                .     '(?'          // Conditional expression follows.
                .         $escaped  // Find end of escaped element.
                .     '|'           // ... else ...
                .         '[^>]*>?' // Find end of normal element.
                .     ')'
                . ')/';
        }

        return $regex;
    }

    /**
     * Newline preservation help function for wpautop
     *
     * @since 3.1.0
     * @access private
     *
     * @param array $matches preg_replace_callback matches array
     * @return string
     */
    public function _autop_newline_preservation_helper($matches)
    {
        return str_replace("\n", '<WPPreserveNewline />', $matches[0]);
    }
}
