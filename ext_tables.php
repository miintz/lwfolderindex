<?php

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

t3lib_extMgm::addStaticFile($_EXTKEY, 'static/lwfolderindextemplate/', 'LWFolderIndexTemplate');

t3lib_div::loadTCA('tx_airfilemanager_dir');
$tempColumns = array(
    'tx_lwfolderindex_flush' => array(
        'exclude' => 0,
        'label' => 'LLL:EXT:lwfolderindex/locallang_db.xml:tx_airfilemanager_dir.tx_lwfolderindex_flush',
        'config' => array(
            'type' => 'check',
            'size' => 30,
            'default' => '0'
        )
    )
);

t3lib_extMgm::addTCAcolumns('tx_airfilemanager_dir', $tempColumns, 1);
t3lib_extMgm::addToAllTCAtypes('tx_airfilemanager_dir', 'tx_lwfolderindex_flush');
$TCA['tx_airfilemanager_dir']['ctrl']['type'] = 'tx_lwfolderindex_flush';

?>