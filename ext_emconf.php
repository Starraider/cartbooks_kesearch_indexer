<?php

/***************************************************************
* Extension Manager/Repository config file for ext "cartbooks_kesearch_indexer".
*
* Auto generated 19-02-2020 09:54
*
* Manual updates:
* Only the data in the array - everything else is removed by next
* writing. "version" and "dependencies" must not be touched!
***************************************************************/

$EM_CONF[$_EXTKEY] = array (
    'title' => 'Faceted Search Indexer for cart_books',
    'description' => 'ke_search indexer for cart_books',
    'category' => 'backend',
    'version' => '1.0.0',
    'state' => 'stable',
    'author' => 'Sven Kalbhenn',
    'author_email' => 'sven@skom.de',
    'author_company' => 'SKom',
    'constraints' => 
    array (
        'depends' => 
        array (
            'typo3' => '8.7.0-9.5.99',
            'cart-books' => '2.0.0-2.99.99',
            'ke_search' => '3.1.0-3.99.99'
        ),
        'conflicts' => 
        array (
        ),
        'suggests' => 
        array (
        ),
    ),
    'uploadfolder' => true,
    'createDirs' => '',
    'clearcacheonload' => true,
);

