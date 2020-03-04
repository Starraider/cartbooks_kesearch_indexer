<?php
// set you own vendor name adjust the extension name part of the namespace to your extension key
namespace Skom\CartbooksKesearchIndexer;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

// set you own class name
class CartBooksIndexer
{
    // Set a key for your indexer configuration.
    // Add this in Configuration/TCA/Overrides/tx_kesearch_indexerconfig.php, too!
    protected $indexerConfigurationKey = 'cartbooksindexer';

    /**
     * Adds the custom indexer to the TCA of indexer configurations, so that
     * it's selectable in the backend as an indexer type when you create a
     * new indexer configuration.
     *
     * @param array $params
     * @param type $pObj
     */
    public function registerIndexerConfiguration(&$params, $pObj)
    {
        // Set a name and an icon for your indexer.
        $customIndexer = array(
            '[CUSTOM] Cart-Books-Indexer (ext:cart_books)',
            $this->indexerConfigurationKey,
            ExtensionManagementUtility::extPath('cartbooks_kesearch_indexer') . 'cartbooks_indexer_icon.svg'
        );
        $params['items'][] = $customIndexer;
    }


    /**
     * Custom indexer for ke_search
     *
     * @param   array $indexerConfig Configuration from TYPO3 Backend
     * @param   array $indexerObject Reference to indexer class.
     * @return  string Message containing indexed elements
     * @author  Sven Kalbhenn <sven@skom.de>
     */
    public function customIndexer(&$indexerConfig, &$indexerObject)
    {
        if ($indexerConfig['type'] == $this->indexerConfigurationKey) {
            $content = '';

            // get all the entries to index
            // don't index hidden or deleted elements, but
            // get the elements with frontend user group access restrictions
            // or time (start / stop) restrictions, in order to copy those restrictions to the index.
            $fields = '*';
            $table = 'tx_cartbooks_domain_model_book';
            $where = 'pid IN (' . $indexerConfig['sysfolder'] . ') AND hidden = 0 AND deleted = 0';
            $groupBy = '';
            $orderBy = '';
            $limit = '';
            $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where, $groupBy, $orderBy, $limit);
            $resCount = $GLOBALS['TYPO3_DB']->sql_num_rows($res);

            // Loop through the records and write them to the index.
            if ($resCount) {
                $counter = 0;
                while (($record = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))) {
                    // compile the information which should go into the index
                    // the field names depend on the table you want to index!
                    $title      = strip_tags($record['title']);
                    $subtitle   = strip_tags($record['subtitle']);
                    $author     = strip_tags($record['author']);
                    $genre      = strip_tags($record['genre']);
                    $teaser     = strip_tags($record['teaser']);
                    $description = strip_tags($record['description']);

                    $fullContent = $title . "\n" . $subtitle . "\n" . $author . "\n" . $genre . "\n" . $teaser . "\n" . $description;

                    // Link to detail view
                    $params = '&cart_books[Book]=' . $record['uid']
                        . '&cart_books[controller]=Books&cart_books[action]=show';

                    // Tags
                    // If you youse Sphinx, use "_" instead of "#" (configurable in the extension manager)
                    $tags = '#books#';

                    // Additional information
                    $additionalFields = array(
                        'sortdate' => $record['crdate'],
                        'orig_uid' => $record['uid'],
                        'orig_pid' => $record['pid'],
                        'sortdate' => $record['datetime'],
                    );
                    $abstract = '<strong>Autor:</strong> '.strip_tags($record['author']).'<br />'.strip_tags($record['teaser']);

                    // add something to the title, just to identify the entries
                    // in the frontend
                    $title = '[Books] ' . $title;

                    // ... and store the information in the index
                    $indexerObject->storeInIndex(
                        $indexerConfig['storagepid'],   // storage PID
                        $title,                         // record title
                        $this->indexerConfigurationKey, // content type
                        $indexerConfig['targetpid'],    // target PID: where is the single view?
                        $fullContent,                   // indexed content, includes the title (linebreak after title)
                        $tags,                          // tags for faceted search
                        $params,                        // typolink params for singleview
                        $abstract,                      // abstract; shown in result list if not empty
                        $record['sys_language_uid'],    // language uid
                        $record['starttime'],           // starttime
                        $record['endtime'],             // endtime
                        $record['fe_group'],            // fe_group
                        false,                          // debug only?
                        $additionalFields               // additionalFields
                    );
                    $counter++;
                }

                $content =
                    '<p><b>Cart Books Indexer "'
                    . $indexerConfig['title'] . '": ' . $counter
                    . ' Books have been indexed.</b></p>';
            }

            return $content;
        }
    }

}