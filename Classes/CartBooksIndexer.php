<?php
// set you own vendor name adjust the extension name part of the namespace to your extension key
namespace Skom\CartbooksKesearchIndexer;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\RootLevelRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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


            ///////////

            //$where = 'pid IN (' .  . ') AND hidden = 0 AND deleted = 0';
            //$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where, $groupBy, $orderBy, $limit);
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_cartbooks_domain_model_book');
            $queryBuilder->getRestrictions()
                ->removeAll()
                ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            $rows = $queryBuilder
                ->select('*')
                ->from('tx_cartbooks_domain_model_book')
                ->where(
                    $queryBuilder->expr()->in(
                        'pid',
                        $indexerConfig['sysfolder']
                     )
                )
                ->orderBy('uid')
                ->execute();
            // Loop through the records and write them to the index.
            $counter = 0;
            while (($record = $rows->fetch())) {
                // compile the information which should go into the index
                // the field names depend on the table you want to index!
                $title      = strip_tags($record['title']);
                $subtitle   = strip_tags($record['subtitle']);
                $author     = strip_tags($record['author']);
                $genre      = strip_tags($record['genre']);
                $teaser     = strip_tags($record['teaser']);
                $description = strip_tags($record['description']);
                $uid = $record['uid'];
                $fullContent = $title . "\n" . $subtitle . "\n" . $author . "\n" . $genre . "\n" . $teaser . "\n" . $description;
                $abstract = strip_tags($record['teaser']);
                // Link to detail view
                //produkt-details?tx_cartbooks_books%5Bbook%5D=4&cHash=1f4d7a47d36da78d70504203a4e99d1a
                //$params = '&tx_cartbooks_books[Book]='.$uid.'&cart_books[controller]=Books&cart_books[action]=show';
                $params = '&tx_cartbooks_books[Book]='.$uid.'&tx_cartbooks_books[controller]=Book&tx_cartbooks_books[action]=show';
                // Additional information
                $additionalFields = array(
                    'sortdate' => $record['crdate'],
                    'orig_uid' => $record['uid'],
                    'orig_pid' => $record['pid'],
                    'sortdate' => $record['datetime'],
                );

                //************* Tags *************/
                // If you youse Sphinx, use "_" instead of "#" (configurable in the extension manager)
                $tags = '';
                //** categories **/
                $categoryQueryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category');
                $categoryQueryBuilder->getRestrictions()
                    ->removeAll()
                    ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
                $categoryRows = $categoryQueryBuilder
                    ->select('uid','title')
                    ->from('sys_category')
                    ->join(
                        'sys_category',
                        'sys_category_record_mm',
                        'm',
                        $categoryQueryBuilder->expr()->eq('m.uid_local', $categoryQueryBuilder->quoteIdentifier('sys_category.uid'))
                        )
                    ->where(
                        $categoryQueryBuilder->expr()->eq('m.uid_foreign', $categoryQueryBuilder->createNamedParameter($uid, \PDO::PARAM_INT))
                    )
                    ->orderBy('sys_category.sorting')
                    ->execute();
                // Loop through the categories and add to tags
                $tagCounter = 0;
                while ($catrecord = $categoryRows->fetch()) {
                    if ($tagCounter > 0){
                        $tags .= ', ';
                    }
                    $tags .= '#'.strip_tags($catrecord['title']).'#';
                    $tagCounter++;
                }
                //** tags **/
                $tagQueryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_cart_domain_model_tag');
                $tagQueryBuilder->getRestrictions()
                    ->removeAll()
                    ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
                $tagRows = $tagQueryBuilder
                    ->select('uid','title')
                    ->from('tx_cart_domain_model_tag')
                    ->join(
                        'tx_cart_domain_model_tag',
                        'tx_cartbooks_domain_model_book_tag_mm',
                        'm',
                        $tagQueryBuilder->expr()->eq('m.uid_foreign', $tagQueryBuilder->quoteIdentifier('tx_cart_domain_model_tag.uid'))
                        )
                    ->where(
                        $tagQueryBuilder->expr()->eq('m.uid_local', $tagQueryBuilder->createNamedParameter($uid, \PDO::PARAM_INT))
                    )
                    ->orderBy('m.sorting')
                    ->execute();
                // Loop through the categories and add to tags
                while ($tagrecord = $tagRows->fetch()) {
                    if ($tagCounter > 0){
                        $tags .= ', ';
                    }
                    $tags .= '#'.strip_tags($tagrecord['title']).'#';
                    $tagCounter++;
                }




                // add something to the title, just to identify the entries
                // in the frontend
                //$title = '[Books] ' . $title;

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
        

            return $content;
        }
    }

}