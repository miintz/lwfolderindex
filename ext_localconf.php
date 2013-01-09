<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

#t3lib_extMgm::addPItoST43($_EXTKEY, 'pi1/class.tx_lwfolderindex_pi1.php', '_pi1', '', 1);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_lwfolderindex_st'] = array(
	'extension'        => $_EXTKEY,
	'title'            => 'Directory index',
	'description'      => 'Directory indexering',
        'additionalFields' => 'tx_lwfolderindex_st_AdditionalFieldProvider'
);

?>