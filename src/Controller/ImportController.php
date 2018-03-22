<?php

namespace Axllent\WeblogWPImport\Control;

use Axllent\WeblogWPImport\Lib\WPXMLParser;
use Axllent\WeblogWPImport\Service\Importer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use SilverStripe\Assets\File;
use SilverStripe\Assets\FileNameFilter;
use SilverStripe\Assets\Image;
use SilverStripe\Blog\Model\Blog;
use SilverStripe\Blog\Model\BlogCategory;
use SilverStripe\Blog\Model\BlogPost;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Session;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SimpleHtmlDom;

set_time_limit(0);

class ImportController extends Controller
{
    private static $allowed_actions = [
        'index',
        'Cancel',
        'UploadForm',
        'Options',
        'OptionsForm',
    ];

    /**
     * @config URLSegment linking lookups
     * Allows you to specify alternate urlsegments for internal linking URL lookups:
     * eg: 'contact-us': 'contact'
     * @param array
     */

    private static $urlsegment_link_rewrite = [];

    private static $url_segment = 'wp-import';

    private static $blog = false;

    public function init()
    {
        parent::init();
        if (!Permission::check('ADMIN')) {
            Security::permissionFailure(null, 'You must be logged in as an administrator to use this tool.');
        }

        Requirements::css('https://fonts.googleapis.com/css?family=Roboto+Slab');
        Requirements::css('axllent/silverstripe-weblog-wp-import: css/milligram.min.css');
        Requirements::css('axllent/silverstripe-weblog-wp-import: css/stylesheet.css');

        $this->session = $this->request->getSession();
    }

    public function index($request)
    {
        if ($this->hasValidUpload()) {
            return $this->redirect(self::$url_segment . '/options/', 302);
        }
        return $this;
    }

    public function Options($request)
    {
        if (!$this->hasValidUpload() || !$this->getBlog()) {
            return $this->redirect(self::$url_segment . '/cancel/', 302);
        }
        return $this->renderWith(['Axllent\\WeblogWPImport\\Control\\SelectionOptions']);
    }

    public function Cancel()
    {
        $this->session->clear('WPExport');
        $this->session->clear('WPImportFieldsSelected');
        return $this->redirect(self::$url_segment . '/', 302);
    }

    public function UploadForm()
    {
        error_log('Upload form');

        if (!extension_loaded('simplexml')) {
            return DBHTMLText::create()
                ->setValue('<p class="message error">This module requires PHP with simplexml</p>');
        }

        $fields = new FieldList(
            $ul = FileField::create('XMLFile', 'Select your WordPress export XML file:'),
            DropdownField::create('BlogID', 'Select your weblog:', Blog::get()->Map('ID', 'MenuTitle'))
        );

        $ul->getValidator()->setAllowedExtensions(['xml']);

        $actions = new FieldList(
            FormAction::create('SaveFile')->setTitle('Upload and analize WordPress XML file »')
        );

        $required = new RequiredFields('XMLFile');

        $form = new Form($this, 'UploadForm', $fields, $actions, $required);

        return $form;
    }

    public function getBlog()
    {
        if (self::$blog) {
            return self::$blog;
        }
        $blog_id = $this->session->get('WPBlog');
        self::$blog = Blog::get()->byID($blog_id);
        return self::$blog;
    }

    public function OptionsForm()
    {
        error_log('Options form');

        $options = [
            'remove_styles_and_classes' => 'Remove all styles & classes',
            'remove_shortcodes' => 'Remove unparsed WordPress shortcodes after all filters',
            'scrape_for_featured_images' => 'Scrape the original site for featured images (<meta property="og:image">)',
        ];

        /* Add option for importing BlogCategory if it exists */
        if (class_exists('Axllent\\Weblog\\Model\\BlogCategory')) {
            $options = array_reverse($options, true);
            $options['categories'] = 'Import blog categories';
            $options = array_reverse($options, true);
        }

        if (BlogPost::get()->filter('ParentID', $this->getBlog()->ID)->Count()) {
            $options = array_reverse($options, true);
            $options['overwrite'] = 'Overwrite/update existing posts (matched by post URLSegment)';
            $options = array_reverse($options, true);
        }

        $default_options = [
            'categories',
            'remove_styles_and_classes',
            'remove_shortcodes'
        ];

        // Must match to html_<variable>($html)
        $filters = [
            'embed_youtube' => 'Link YouTube videos',
            'remove_divs' => 'Remove div elements (not innerHTML)',
            'remove_spans' => 'Remove span elements (not innerHTML)',
            'clean_trim' => 'Remove leading/trailing empty paragraphs',
        ];

        $default_filters = array_keys($filters);

        if ($this->session->get('WPImportFieldsSelected')) {
            $default_options = [];
            $default_filters = [];
        };

        $fields = new FieldList(
            CheckboxSetField::create(
                'WPImportOptions',
                'Choose the import options:',
                $options,
                $default_options
            ),
            NumericField::create('set_image_width', 'Set post image widths: (If used all then ' .
                'locally hosted images will be set to this size provided they are large enough)'),
            CheckboxSetField::create(
                'WPImportFilters',
                'Choose the import filters:',
                $filters,
                $default_filters
            )
        );

        $actions = new FieldList(
            FormAction::create('ProcessImport')->setTitle('Process Options »')
        );

        $required = new RequiredFields([]);

        $form = new Form($this, 'OptionsForm', $fields, $actions, $required);

        return $form;
    }

    public function ProcessImport($data, $form)
    {
        error_log('Processing import');

        $form->setSessionData($data);
        $this->session->set('WPImportFieldsSelected', 'true');

        $importer = new Importer();

        $xml = $this->session->get('WPExport');
        if (!$xml) {
            $form->sessionMessage('No valid data found');
            return false;
        } else {
            $importer->getImportData($xml);
        }


        $process_categories = !empty($data['WPImportOptions']['categories']) ? : false;
        $overwrite = !empty($data['WPImportOptions']['overwrite']) ? true : false;
        $remove_shortcodes = !empty($data['WPImportOptions']['remove_shortcodes']) ? true : false;
        $remove_styles_and_classes = !empty($data['WPImportOptions']['remove_styles_and_classes']) ? true : false;
        $scrape_for_featured_images = !empty($data['WPImportOptions']['scrape_for_featured_images']) ? true : false;
        $set_image_width = !empty($data['set_image_width']) ? $data['set_image_width'] : false;
        $import_filters = !empty($data['WPImportFilters']) ? $data['WPImportFilters'] : false;
        $urlsegment_link_rewrite = $this->config()->get('urlsegment_link_rewrite');

        $status = []; // Form return

        $blog = $this->getBlog();

        if ($process_categories) {
            $status = $importer->processCategories($blog, $status);
        }

        // Counters for form return
        $blog_posts_added = 0;
        $blog_posts_updated = 0;
        $assets_downloaded = 0;

        list($blog_posts_added, $blog_posts_updated, $assets_downloaded) = $this->importPosts($data, $import, $overwrite, $blog_posts_added, $blog_posts_updated, $blog, $import_filters, $remove_styles_and_classes, $assets_downloaded, $set_image_width, $urlsegment_link_rewrite, $matches, $remove_shortcodes, $scrape_for_featured_images, $process_categories);

        $status[] = $blog_posts_added . ' posts added';

        if ($overwrite) {
            $status[] = $blog_posts_updated . ' posts updated';
        }

        $status[] = $assets_downloaded . ' assets downloaded';

        $form->sessionMessage(implode($status, ', '), 'good');

        return $this->redirectBack();
    }

    public function hasValidUpload()
    {
        $session = $this->request->getSession();

        return $session->get('WPExport') ? true : false;
    }

    public function getSessionXML()
    {
        $session = $this->request->getSession();

        return $session->get('WPExport');
    }

    public function SaveFile($data, $form)
    {
        $blog_id = $data['BlogID'];

        $blog = Blog::get()->byID($data['BlogID']);
        if (!$blog) {
            $form->sessionMessage('Please select a valid Blog.');
            return $this->redirectBack();
        }

        $file = $data['XMLFile'];

        if (empty($file['tmp_name'])) {
            $form->sessionMessage('Please upload a valid XML file.');
            return $this->redirectBack();
        }
        // raw xml string
        $content = file_get_contents($file['tmp_name']);

        $parser = new WPXMLParser($content);

        if (!$parser->xml) {
            $form->sessionMessage('File could not be parsed. Please make sure the file is a valid WordPress export XML file.');
            return $this->redirectBack();
        }

        $session = $this->request->getSession();

        $session->set('WPExport', $content);
        $session->set('WPBlog', $blog_id);

        return $this->redirect(self::$url_segment . '/options/');
    }



    /**
     * HTTP wrapper
     * @param String
     * @return String
     */
    public function getRemoteFile($url)
    {
        $body = false;

        $client = new Client([
            'timeout'  => 10,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; rv:49.0) Gecko/20100101 Firefox/49.0',
                'Accept-Language' => 'en-US,en;q=0.5'
            ],
        ]);
        try {
            $response = $client->get($url);
            $code = @$response->getStatusCode();
            if ($code == 200 || $this->force_cache) { // don't cache others
                $body = $response->getBody()->getContents();
                return $body;
            }
        } catch (RequestException $e) {
            // ignore
        }

        unset($client);
        $client = null;

        return $body;
    }




}
