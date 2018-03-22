<?php

namespace Axllent\WeblogWPImport\Lib;

use SilverStripe\View\ArrayData;
use SilverStripe\ORM\ArrayList;

class WPXMLParser
{
    public function __construct(string $xml)
    {
        $xml = $this->stripInvalidXml($xml);
        $this->xml = @simplexml_load_string($xml);
    }

    /**
     * Parse XML file
     * Does not modify any content
     * @param NULL
     * @return DataList
     */
    public function XML2Data()
    {
        if (!$this->xml) {
            return false;
        }

        $namespaces = $this->xml->getNamespaces(true);

        $this->wp_export_format = isset($namespaces['wp']) ?
            $namespaces['wp'] : 'http://wordpress.org/export/1.2/';

        $this->wp_content_format = isset($namespaces['content']) ?
            $namespaces['content'] : 'http://purl.org/rss/1.0/modules/content/';

        $posts = [];

        $info = $this->xml->channel->children($this->wp_export_format);

        $output = ArrayData::create([
            'SiteURL' => rtrim((string)$info->base_site_url, '/') . '/',
            'Categories' => ArrayList::create(),
            'Tags' => ArrayList::create(),
            'Posts' => ArrayList::create()
        ]);

        // an item is a post
        foreach ($this->xml->channel->item as $item) {
            error_log('>>> PARSING POST ' . $item->title);
            $categories = ArrayList::create();
            $tags = ArrayList::create();
            $comments = ArrayList::create();
            foreach ($item->category as $category) {
                if ($category['nicename'] != 'uncategorized' && $category['domain'] == 'category') {
                    $categories->push(ArrayData::create([
                        'URLSegment' => trim((string)$category['nicename']),
                        'Title' => trim(html_entity_decode((string)$category))
                    ]));
                    error_log('==> FOUND A CATEGORY');
                }

                if ($category['nicename'] != 'uncategorized' && $category['domain'] == 'post_tag') {
                    $tags->push(ArrayData::create([
                        'URLSegment' => trim((string)$category['nicename']),
                        'Title' => trim(html_entity_decode((string)$category))
                    ]));

                    error_log('==> FOUND A TAG');
                }
            }


            $namespaces = $item->getNameSpaces(true);
            $wp = $item->children($namespaces['wp']);

            foreach($wp->comment as $commentData)
            {
                error_log('COMMENT!');

                $comments->push(ArrayData::create([

                    'WordPressID' => (int)($commentData->comment_id),
                    'Email' => (string)($commentData->comment_author_email),
                    'Name' => (string)($commentData->comment_author),
                    'URL' => (string)($commentData->comment_author_url),
                    'Created' => (string)($commentData->comment_date_gmt),
                    'LastEdited' => (string)($commentData->comment_date_gmt),
                    'Content' => (string)($commentData->comment_content)
                ]));

                /*
                 * <wp:comment>
			//<wp:comment_id>6</wp:comment_id>
			//<wp:comment_author><![CDATA[hgaxzszs]]></wp:comment_author>
			//<wp:comment_author_email>stmven@qnupqm.com</wp:comment_author_email>
		//	<wp:comment_author_url>http://womavkjhdomg.com/</wp:comment_author_url>
			<wp:comment_author_IP>96.228.65.241</wp:comment_author_IP>
			<wp:comment_date>2010-08-28 18:20:14</wp:comment_date>
			<wp:comment_date_gmt>2010-08-28 18:20:14</wp:comment_date_gmt>
			<wp:comment_content><![CDATA[8OSFds  <a href="http://ofncovaiclms.com/" rel="nofollow">ofncovaiclms</a>, [url=http://hrisachyhbdu.com/]hrisachyhbdu[/url], [link=http://voydlfevnshf.com/]voydlfevnshf[/link], http://mgocdwviblcu.com/]]></wp:comment_content>
			<wp:comment_approved>0</wp:comment_approved>
			<wp:comment_type></wp:comment_type>
			<wp:comment_parent>0</wp:comment_parent>
			<wp:comment_user_id>0</wp:comment_user_id>
		</wp:comment>

                | ID              | int(11)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | NO   | PRI | NULL                                | auto_increment |
| ClassName       | enum('SilverStripe\\Comments\\Model\\Comment')                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | YES  | MUL | SilverStripe\Comments\Model\Comment |                |
| LastEdited      | datetime                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         | YES  |     | NULL                                |                |
| Created         | datetime                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         | YES  | MUL | NULL                                |                |
| Name            | varchar(200)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     | YES  |     | NULL                                |                |
| Comment         | mediumtext                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       | YES  |     | NULL                                |                |
| Email           | varchar(200)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     | YES  |     | NULL                                |                |
| URL             | varchar(255)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     | YES  |     | NULL                                |                |
| Moderated       | tinyint(1) unsigned                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              | NO   |     | 0                                   |                |
| IsSpam          | tinyint(1) unsigned                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              | NO   |     | 0                                   |                |
| AllowHtml       | tinyint(1) unsigned                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              | NO   |     | 0                                   |                |
| SecretToken     | varchar(255)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     | YES  |     | NULL                                |                |
| Depth           | int(11)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | NO   |     | 0                                   |                |
| AuthorID        | int(11)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | NO   | MUL | 0                                   |                |
| ParentCommentID | int(11)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | NO   | MUL | 0                                   |                |
| ParentID        | int(11)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | NO   | MUL | 0                                   |                |
| ParentClass     | enum('SilverStripe\\Assets\\File','SilverStripe\\SiteConfig\\SiteConfig','SilverStripe\\Versioned\\ChangeSet','SilverStripe\\Versioned\\ChangeSetItem','WebOfTalent\\TwitterTools\\EmbeddedTweet','WebOfTalent\\TwitterTools\\EmbeddedTweetAuthor','SilverStripe\\Blog\\Model\\BlogCategory','SilverStripe\\Blog\\Model\\BlogTag','SilverStripe\\CMS\\Model\\SiteTree','SilverStripe\\Comments\\Model\\Comment','SilverStripe\\Security\\Group','SilverStripe\\Security\\LoginAttempt','SilverStripe\\Security\\Member','SilverStripe\\Security\\MemberPassword','SilverStripe\\Security\\Permission','SilverStripe\\Security\\PermissionRole','SilverStripe\\Security\\PermissionRoleCode','SilverStripe\\Security\\RememberLoginHash','SilverStripe\\Widgets\\Model\\Widget','SilverStripe\\Widgets\\Model\\WidgetArea','Symbiote\\QueuedJobs\\DataObjects\\QueuedJobDescriptor','Symbiote\\QueuedJobs\\DataObjects\\QueuedJobRule','SilverStripe\\Assets\\Folder','SilverStripe\\Assets\\Image','Page','SilverStripe\\ErrorPage\\ErrorPage','SilverStripe\\Blog\\Model\\Blog','SilverStripe\\Blog\\Model\\BlogPost','SilverStripe\\CMS\\Model\\RedirectorPage','SilverStripe\\CMS\\Model\\VirtualPage','Suilven\\HomeLandingPage\\Model\\HomePage','Suilven\\HomeLandingPage\\Model\\LandingPage','SilverStripe\\Blog\\Widgets\\BlogArchiveWidget','SilverStripe\\Blog\\Widgets\\BlogCategoriesWidget','SilverStripe\\Blog\\Widgets\\BlogRecentPostsWidget','SilverStripe\\Blog\\Widgets\\BlogTagsCloudWidget','SilverStripe\\Blog\\Widgets\\BlogTagsWidget') | YES  |     | SilverStripe\Assets\File            |                |
+-----------------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+------+-----+-------------------------------------+----------------+
17 rows in set (0.01 sec)

                 */
            }

            $content_obj = $item->children($this->wp_content_format);

            $content = (string)$content_obj->encoded;

            // Convert publish date
            $publish_date = date('Y-m-d H:i:s', strtotime((string)$item->pubDate));

            $wp = $item->children($this->wp_export_format);

            $post = ArrayData::create([
                'Title' => trim((string)$item->title),
                'URLSegment' => (string)$wp->post_name,
                'Link' => (string)$item->link,
                'PublishDate' => $publish_date,
                'Categories' => $categories,
                'Comments' => $comments,
                'Tags' => $tags,
                'Status' => (string)$wp->status,
                'Content'=> $content,
            ]);

            $output->Posts->add($post);
        }

        return $output;
    }

    /**
     * Remove invalid characters from XML data
     * @param String
     * @return String
     */
    public function stripInvalidXml($xml)
    {
        $ret = '';
        $current;
        if (empty($xml)) {
            return $ret;
        }

        $length = strlen($xml);
        for ($i=0; $i < $length; $i++) {
            $current = ord($xml{$i});
            if (($current == 0x9) ||
                ($current == 0xA) ||
                ($current == 0xD) ||
                (($current >= 0x20) && ($current <= 0xD7FF)) ||
                (($current >= 0xE000) && ($current <= 0xFFFD)) ||
                (($current >= 0x10000) && ($current <= 0x10FFFF))) {
                $ret .= chr($current);
            } else {
                $ret .= ' ';
            }
        }
        return $ret;
    }


    ### The following functions are taken from the existing WordPress libraries ###

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
