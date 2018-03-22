<?php
/**
 * Created by PhpStorm.
 * User: gordon
 * Date: 22/3/2561
 * Time: 20:56 น.
 */
namespace Axllent\WeblogWPImport\Task;

use Axllent\WeblogWPImport\Service\Importer;
use SilverStripe\Blog\Model\Blog;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

class ImportWordPressTask extends BuildTask
{
    protected $title = 'Say Hi';

    protected $description = 'A class that says <strong>Hi</strong>';

    protected $enabled = true;

    private static $segment = 'importWP';

    function run($request) {
        $canAccess = ( \SilverStripe\Control\Director::isDev() || \SilverStripe\Control\Director::is_cli() || Permission::check("ADMIN") );
        if (!$canAccess) {
            return Security::permissionFailure($this);
        }
        $exportFile = $_GET['exportFile'];
        $blogID = $_GET['blog_id'];
        $blog = DataObject::get_by_id('SilverStripe\Blog\Model\Blog', $blogID);

        $xml = file_get_contents($exportFile, FILE_USE_INCLUDE_PATH);

        $importer = new Importer($blog);
        $importer->getImportData($xml);
        $importer->processCategories();
        $importer->processPosts();
       // $importer->processTags();


    }
}

