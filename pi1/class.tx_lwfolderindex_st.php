<?php

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Maarten van Hees <maarten@lingewoud.com>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

//require_once(PATH_tslib . 'class.tslib_pibase.php');

/**
 * Plugin 'fmain' for the 'lwfolderindex' extension.
 *
 * @author	Maarten van Hees <maarten@lingewoud.com>
 * @package	TYPO3
 * @subpackage	tx_lwfolderindex
 */

define('PATH_tslib', PATH_site . 'typo3/sysext/cms/tslib/');

class tx_lwfolderindex_st extends tx_scheduler_Task {

    public $extKey = 'lwfolderindex'; // The extension key.
    
    public $root;
    public $shortroot;
    
    public function execute() {
        
        $this->getTSFEobject();
  
        $this->root = PATH_site . rtrim($this->rootdir, "/");
        $this->shortroot = rtrim($this->rootdir, "/");
        t3lib_div::devLog("indexing for: " . $this->root, 1);
        
        $insertrows = array();
        
        /*
         * 'insert' => ($fieldnames, $rows)
         * 'delete' => ($query)
         * 'update' => ($where, $values)
         */
        
        $querylist = $this->recursiveIndexing($this->root, $insertrows);
        
        if(count($insertrows) > 0)
        {
            $fieldnames = array_keys($insertrows[0]);
            
            t3lib_div::devLog("inserting", 1, 0, array($fieldnames, $insertrows));        
            
            $GLOBALS['TYPO3_DB']->exec_INSERTmultipleRows('tx_airfilemanager_dir', $fieldnames, $insertrows);            
        }
        
        t3lib_div::devLog("indexing done", $this->extKey);
        
        return true;
    }

    private function recursiveIndexing($basedir = null, &$insertrows) 
    {
        //nu kijken welke folders zich hier in bevinden
        $innerdirs = array_diff(scandir($basedir), array(".", ".."));
        
        //nu per dir kijken wat er in zit
        foreach ($innerdirs as $dir) {
            //elke dir krijgt een array
            if (is_dir($basedir . "/" . $dir)) {
                
                $this->indexFolder($dir, $this->getDirPath($basedir), $basedir, $insertrows);
                
                $this->recursiveIndexing($basedir . "/" . $dir, $insertrows);
            }
        }

        return $folderarray;
    }
    
    function getDirPath($base)
    {
        //this path might not be relative        
        $delim = rtrim($this->rootdir, '/'); //!!!!!
        
        $basearray = split($delim, $base);
        
        
        
        $key = array_search($delim, $basearray);
        
        //get rid of everything before the root folder
        
        $basearray[0] = $delim;
        
        if(!$basearray[1])
        {
            t3lib_div::devLog("BASEARRAY", $this->extKey, 0, array($gluedbase, $base, $basearray));
        
            return $delim;    
        }
        
        $basearray[1] = ltrim($basearray[1], '/');
        
        //glue it back together
        //$newbase = array_values($basearray);
        $gluedbase = implode("/", $basearray);
        
        return $gluedbase;
    }
    
    function getContainingDirname($base)
    {
        //return the containing folder
        $basearray =  split("/", $base);
        
        unset($basearray[count($basearray)]);
        $newbasearray = array_values($basearray);
        
        return $newbasearray[count($newbasearray) - 1];
    }
    
    function getContainingDirpath($base)
    {
        //return the dirname of the containing folder
        $basearray = split("/", $this->getDirPath($base));
        
        unset($basearray[count($basearray) - 1]);
        
        $newbasearray = array_values($basearray);
        
        return implode("/", $newbasearray);
    }
    
    function flushFolder($fullbase, &$rquery, &$aclquery)
    {   
        //combinatie van $base + $folder is het volledige path
        
        //we slopen alle records eruit die in deze map zitten, dus niet de map zelf (we hebben de ACLs nodig)
        $innerdirs = array_diff(scandir($fullbase), array(".", "..")); 
        
        foreach ($innerdirs as $dir) 
        {
            //elke dir krijgt een array
            if (is_dir($fullbase . "/" . $dir)) 
            {    
                //zoek de UID op van deze index                
                $where = "dir_name= '" . $dir . "' AND dir_path= '" . $this->getDirPath($fullbase) . "' AND ! deleted";
                $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery( '*', 'tx_airfilemanager_dir', $where);                                             
                
                if($res && $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) 
                {  
                    //deze map is geindexeert, voeg de uid toe aan de rquery
                    $uid = $row['uid'];                    
                    
                    if($rquery == "") {
                        $rquery = "uid = " . $uid; 
                        $aclquery = "foreign_uid = " . $uid; 
                    }
                    else {
                        $rquery .= " OR uid = " . $uid;
                        $aclquery .= " OR foreign_uid = " . $uid; 
                    }
                }
                
                $this->flushFolder($fullbase . "/" . $dir, $rquery, $aclquery);
            }
        }
    }
    
    function indexFolder($folder, $base, $fullpath, &$insertrows)
    {
        //check if index has allready taken place
        $where = "dir_name='" . $folder . "' AND dir_path='" . $base . "' AND ! deleted";
        $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery( '*', 'tx_airfilemanager_dir', $where );
        
        if($res && $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) 
        {
            
            //this folder has allready been indexed. but perhaps the user has ticked the Flush box
            
            if($row['tx_lwfolderindex_flush'] == 1)
            {               
                //folder needs to be flushed, go and do that now.
                $rquery = "";
                $aclquery = "";
                
                if($base != $this->shortroot)
                {
                    $dirs = explode("/", $base);
                    
                    $fullbase = $this->root . '/' . $dirs[count($dirs) - 1]; //+rechtentest 
                }
                else
                    $fullbase = $this->root;
                
                $this->flushFolder($fullbase . '/' . $folder, $rquery, $aclquery);
                                
                if($rquery != "")
                    $GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_airfilemanager_dir', $rquery);
                
                if($aclquery != "")
                    $GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_airfilemanager_acl', $aclquery);
                
                //stop force update in fullbase
                $fieldvals = array();
                $fieldvals['tx_lwfolderindex_flush'] = 0;
                $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_airfilemanager_dir', 'uid = ' . $row['uid'], $fieldvals);
                
                return;
            }
            else
                return;
        }
        
        //no index yet, index it now 
        $fields = Array();
        $fields['pid'] = tx_dam_db::getPid(); //$this->storage;
        $fields['crdate'] = time();
        $fields['tstamp'] = time();

        //set the rights, or rather, nick them from the above folder...        
        
        $fields['dir_name'] = $folder;//$this->name;
        $fields['dir_path'] = $base;//$this->getPath();
        $fields['title'] = $folder;
        
        //before we insert it we should set the rights, use the rights from the containing folder
        $containerdirname = $this->getContainingDirname($fullpath);
        $containerdirpath = $this->getContainingDirpath($fullpath);                
        
        //dir_path = pad vanaf root
        //dir_name = naam van map
        
        if(!$containerdirpath)
        {                        
            //$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_airfilemanager_dir', );
            
            $fields['fe_user'] = null;
            $fields['fe_group'] = 0;
            $fields['ac_owner'] = 15;
            $fields['ac_group'] = 15; 
            $fields['ac_login'] = 9;
            $fields['ac_other'] = 9;
            $fields['access_lists'] = null;
            $fields['hidden'] = '1';
              
            $insertrows[] = $fields;
            
            return;
        }
        else
        {   
            $container;
            
            foreach($insertrows as $row)
            {
                //geen closure mogelijk, doe een loop.
                if($row['dir_name'] == $containerdirname && $row['dir_path'] == $containerdirpath)
                {
                    $container = $row;

                    t3lib_div::devLog("no closure",1,0,array($container));
                    break;
                }
            }
            
            /* when updating from < 5.2 to > 5.3 use this instead of above loop
            if(version_compare(PHP_VERSION, '5.3.0') >= 0) 
            {
                //maak een closure! heuj, 5.3 ftw. 
                t3lib_div::devLog("closure",1);
                $container = array_filter($insertrows, array(new DirpathFinder($containerdirpath, $containerdirname), 'isDirpath'));
            }
            else 
            {
                foreach($insertrows as $row)
                {
                    //geen closure mogelijk, doe een loop.
                    if($row['dir_name'] == $containerdirname && $row['dir_path'] == $containerdirpath)
                    {
                        $container = $row;
                        
                        t3lib_div::devLog("no closure",1,0,array($container));
                        break;
                    }
                }
            }
            */
            
            $container = array_values($container);
            $container = $container[0];
            
            /*
             * dit werkt niet altijd, als er flush is geweest bestaan bepaalde parents niet. 
             * 
             * dus als we hier zijn aangekomen is er geen index geweest maar bestaat mogelijk de parent niet vanwege
             * een mogelijke flush. daarom even kijken of de filter is retourneerd   
             * 
             * maar als een map onder een geindexeerde map erin moet bestaat de benodigde data dus wel. dus als
             * container leeg is staat deze mogelijk in de database
             */
        
            t3lib_div::devLog("sjoep",1,0,array($containerdirname, $containerdirpath));
            
            if(!$container)
            {               
                //check db
                $where = " dir_name = '" . $containerdirname . "' AND dir_path = '" . $containerdirpath . "' AND !deleted";
                $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_airfilemanager_dir', $where);
                
                $container = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);            
            }
            
            t3lib_div::devLog("de filter: ",12,0,array($container));
            
            if($container)
            {
                $fields['fe_user'] = $container['fe_user'];
                $fields['fe_group'] = $container['fe_group']; 
                $fields['ac_owner'] = $container['ac_owner'];
                $fields['ac_group'] = $container['ac_group'];
                $fields['ac_login'] = $container['ac_login'];
                $fields['ac_other'] = $container['ac_other'];
                $fields['access_lists'] = $container['access_lists'];
                $fields['hidden'] = $container['hidden'];
                
                //that should sort us out, insert it into the table
                
                $insertrows[] = $fields;
                    
                t3lib_div::devLog("ressing 2", 1,0, array($insertrows));
                
                $inserted = $fields;
                
                //in dit geval is het de laatste uid + positie van de insertrow - 1
                $inf = $GLOBALS['TYPO3_DB']->sql_query("SHOW TABLE STATUS LIKE 'tx_airfilemanager_dir'");
                $infrow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($inf);
                               
                $maxuid = $infrow['Auto_increment'];
                               
                $rowpos = count($insertrows) - 1;
                
                $inserted['uid'] = $maxuid + $rowpos;
                                                         
                if(!$container['uid'])
                    $container['uid'] = $inserted['uid'] - 1;
                                   
                //t3lib_div::devLog("UFUID", 1,0,array($maxuid, $inserted['uid'], $container['uid']));
                
                //now that we have the container record, we can also find the ACLs
                $aclwhere = "foreign_uid='".$container['uid']."'";
                $acls = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_airfilemanager_acl', $aclwhere);
                
                if($acls)
                {
                    //copy acls to new record
                    while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($acls))
                    {
                        $row['tstamp'] = time();
                        $row['foreign_uid'] = $inserted['uid']; //dit word de laatste uid 
                        
                        unset($row['uid']);
                        
                        t3lib_div::devLog("ACL copied", $this->extKey, 0, array($row, $inserted));
                        
                        //insert copied ACL
                        $GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_airfilemanager_acl',$row);
                    }   
                }
            }
            else
            {
                //toplevel

                $fields['fe_user'] = null;
                $fields['fe_group'] = 0;
                $fields['ac_owner'] = 15;
                $fields['ac_group'] = 15; 
                $fields['ac_login'] = 9;
                $fields['ac_other'] = 9;
                $fields['access_lists'] = null;
                $fields['hidden'] = '1';

                $insertrows[] = $fields;

                return;
            }  
            
            return;
        }
    }
    
    /* uncomment when going to >=5.3
    function create_dirpath_finder($dirname) 
    {
        // The "use" here binds $dirname to the function at declare time.
        // This means that whenever $dirname appears inside the anonymous
        // function, it will have the value it had when the anonymous
        // function was declared.
        
        //maak een closure! heuj, 5.3 ftw
        return function(array $test) use($dirname) { return $test['dir_path'] == $dirname; };
        
    }
    */
    
    function getTSFEobject($pid = 1) 
    {
        require_once (PATH_site . 'typo3/sysext/cms/tslib/class.tslib_fe.php');
        require_once (PATH_site . 't3lib/class.t3lib_userauth.php');
        require_once (PATH_site . 'typo3/sysext/cms/tslib/class.tslib_feuserauth.php');
        require_once (PATH_site . 't3lib/class.t3lib_cs.php');
        require_once (PATH_site . 'typo3/sysext/cms/tslib/class.tslib_content.php');
        require_once (PATH_site . 't3lib/class.t3lib_tstemplate.php');
        require_once (PATH_site . 't3lib/class.t3lib_page.php');
        require_once (PATH_site . 't3lib/class.t3lib_timetrack.php');
        
        // Finds the TSFE classname
        // $TSFEclassName = t3lib_div::makeInstance('tslib_fe');
        // Create the TSFE class.
        // $GLOBALS['TSFE'] = new $TSFEclassName($GLOBALS['TYPO3_CONF_VARS'], $pid, '0', 0, '','','','');

        $GLOBALS['TT'] = t3lib_div::makeInstance('t3lib_timeTrack');
        $GLOBALS['TT']->start();
        $GLOBALS['TSFE'] = t3lib_div::makeInstance('tslib_fe', $GLOBALS['TYPO3_CONF_VARS'], $pid, '0', 0, '', '', '', '');
        // $temp_TTclassName = t3lib_div::makeInstance('t3lib_timeTrack');
        // $GLOBALS['TT'] = new $temp_TTclassName();

        $GLOBALS['TSFE']->config['config']['language'] = $_GET['L'];
      
        // Fire all the required function to get the typo3 FE all set up.
        $GLOBALS['TSFE']->id = $pid;
        $GLOBALS['TSFE']->connectToDB();
        // Prevent mysql debug messages from messing up the output
        $sqlDebug = $GLOBALS['TYPO3_DB']->debugOutput;
        $GLOBALS['TYPO3_DB']->debugOutput = false;
        $GLOBALS['TSFE']->initLLVars();
        $GLOBALS['TSFE']->initFEuser();
        // Look up the page
        $GLOBALS['TSFE']->sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
        $GLOBALS['TSFE']->sys_page->init($GLOBALS['TSFE']->showHiddenPage);
          
        // If the page is not found (if the page is a sysfolder, etc), then return no URL, preventing any further processing which would result in an error page.
        $page = $GLOBALS['TSFE']->sys_page->getPage($pid);
        // $GLOBALS['TSFE']->page = $page;
        
        if (count($page) == 0) {
            $GLOBALS['TYPO3_DB']->debugOutput = $sqlDebug;
            return false;
        }
       
        // If the page is a shortcut, look up the page to which the shortcut references, and do the same check as above.
        
        if ($page['doktype'] == 4 && count($GLOBALS['TSFE']->getPageShortcut($page['shortcut'], $page['shortcut_mode'], $page['uid'])) == 0) {
            $GLOBALS['TYPO3_DB']->debugOutput = $sqlDebug;
            return false;
        }
        
        // Spacer pages and sysfolders result in a page not found page too…
        if ($page['doktype'] == 199 || $page['doktype'] == 254) {
            $GLOBALS['TYPO3_DB']->debugOutput = $sqlDebug;
            return false;
        }
        
        $GLOBALS['TSFE']->getPageAndRootline();
        $GLOBALS['TSFE']->initTemplate();
        $GLOBALS['TSFE']->forceTemplateParsing = 1;
        
        // Find the root template
      
        $GLOBALS['TSFE']->tmpl->start($GLOBALS['TSFE']->rootLine);
        
        // Fill the pSetup from the same variables from the same location as where tslib_fe->getConfigArray will get them, so they can be checked before this function is called
        
        $GLOBALS['TSFE']->sPre = $GLOBALS['TSFE']->tmpl->setup['types.'][$GLOBALS['TSFE']->type]; // toplevel - objArrayName
        $GLOBALS['TSFE']->pSetup = $GLOBALS['TSFE']->tmpl->setup[$GLOBALS['TSFE']->sPre . '.'];
        
        // If there is no root template found, there is no point in continuing which would result in a 'template not found' page and then call exit php. Then there would be no clickmenu at all.
        // And the same applies if pSetup is empty, which would result in a \\\"The page is not configured\\\" message.
        
        if (!$GLOBALS['TSFE']->tmpl->loaded || ($GLOBALS['TSFE']->tmpl->loaded && !$GLOBALS['TSFE']->pSetup)) {
            $GLOBALS['TYPO3_DB']->debugOutput = $sqlDebug;
            return false;
        }
        
        $GLOBALS['TSFE']->getConfigArray();
        $GLOBALS['TSFE']->getCompressedTCarray();
        $GLOBALS['TSFE']->inituserGroups();
        $GLOBALS['TSFE']->connectToDB();
        $GLOBALS['TSFE']->determineId();
        
        //maak content object
        $GLOBALS['TSFE']->newCObj();
        
        //t3lib_div::devLog("Stap 7", $this->extKey, 0, array((array)$GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_airfilemanager_pi1.']));
    }
}

/* this is for 5.3
class DirpathFinder {
        private $dirpath;
        private $dirname;
        
        function __construct($dirpath, $dirname) {
                $this->dirpath = $dirpath;
                $this->dirname = $dirname;
        }

        function isDirpath($i) 
        {
                return ($i['dir_path'] == $this->dirpath && $i['dir_name'] == $this->dirname);
        }        
}
*/

?>