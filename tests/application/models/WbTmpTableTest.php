<?php

require_once APPLICATION_PATH . '/models/WbTmpTable.php';
require_once APPLICATION_PATH . '/models/Client.php';
require_once APPLICATION_PATH . '/models/Job.php';
require_once 'PHPUnit/Framework/TestCase.php';

/**
 * WbTmpTable test case.
 */
class WbTmpTableTest extends PHPUnit_Framework_TestCase {
	
	// from controllers/RestorejobController.php
	const _PREFIX = '_'; // только в нижнем регистре
	const _PREFIX_RECENT = '_recent_'; // для восстановления типа Restore recent backup. только в нижнем регистре
	protected $restoreNamespace; 
	const RESTORE_NAME_SPACE = 'RestoreSessionNamespace';
	protected $ttl_restore_session = 3900; // time to live session (65 min)
	protected $jobid = 1;
	protected $jobHashRecent;
	
	private $WbTmpTable;
	private $WbTmpTableRecent;
	
	/**
	 * Prepares the environment before running a test.
	 */
	protected function setUp() {
		parent::setUp ();
		// session begin
		$this->restoreNamespace = new Zend_Session_Namespace(self::RESTORE_NAME_SPACE);
	   	$this->restoreNamespace->setExpirationSeconds($this->ttl_restore_session);
	   	Zend_Session::rememberMe($this->ttl_restore_session);
	   	// запоминаем данные в сессии
		$this->restoreNamespace->typeRestore = 'restore';
		$this->restoreNamespace->JobId = $this->jobid;
		$this->restoreNamespace->JobHash = md5($this->jobid);
		$this->WbTmpTable = new WbTmpTable(self::_PREFIX, md5($this->jobid));
	}
	
	/**
	 * Cleans up the environment after running a test.
	 */
	protected function tearDown() {
		$this->WbTmpTable = null;
		session_write_close();
		parent::tearDown();
	}
	
	/**
	 * Constructs the test case.
	 */
	public function __construct() {
		parent::__construct();
		// empty
	}
	
	/**
	 * @group restore
	 */
	public function testCloneBaculaToTmp() {
		print "\n".__METHOD__.' ';
		// собственно клонирование
		$this->WbTmpTable->cloneBaculaToTmp($this->jobid);
		// проверяем кол-во
		$res = $this->WbTmpTable->getCountFile();
		$this->assertTrue($res == 3608, __FUNCTION__." failed (count files = $res)");
		$res = $this->WbTmpTable->getCountFileName();
		$this->assertTrue($res == 3601, __FUNCTION__." failed (count file names = $res)");
		$res = $this->WbTmpTable->getCountPath();
		$this->assertTrue($res == 8, __FUNCTION__." failed (count paths = $res)");
	}
	
	/**
    * @group restore
    */
	public function testMarkFile() {
		print "\n".__METHOD__.' ';
		$this->WbTmpTable->markFile(100);
		$this->WbTmpTable->markFile(101);
		// получаем суммарную статистику
    	$ares = $this->WbTmpTable->getTotalSummaryMark();
    	$this->assertTrue($ares['total_files'] == 2, __FUNCTION__." total files count = " . 
    		$ares['total_files'] . " failed");
	}
	
	/**
    * @group restore
    */
	public function testUnmarkFile() {
		print "\n".__METHOD__.' ';
		$this->WbTmpTable->unmarkFile(100);
		$this->WbTmpTable->unmarkFile(101);
		// получаем суммарную статистику
    	$ares = $this->WbTmpTable->getTotalSummaryMark();
    	$this->assertTrue($ares['total_files'] == 0, __FUNCTION__." total files count = " . 
    		$ares['total_files'] . " failed");
	}

	/**
    * @group restore
    */
	public function testMarkDir() {
		print "\n".__METHOD__.' ';
		$this->WbTmpTable->markDir("/tmp/webacula/test/1/0 Каталог'tmp/", 1);
		// получаем суммарную статистику
    	$ares = $this->WbTmpTable->getTotalSummaryMark();
    	$this->assertTrue($ares['total_files'] == 3607, __FUNCTION__." total files count = " . 
	    	$ares['total_files'] . " failed");
	}
	
	/**
    * @group restore
    */
	function testExportMarkFiles() {
		print "\n".__METHOD__.' ';
		$ares = $this->WbTmpTable->exportMarkFiles('/tmp');
		$this->assertTrue( ( $ares['result'] && ($ares['count'] == 3607) ), 'error export marked files');
		unlink( '/tmp/'.$this->WbTmpTable->getFilenameToExportMarkFiles() ); 
	}
	
	/**
    * @group restore
    */
	public function testUnMarkDir() {
		print "\n".__METHOD__.' ';
		$this->WbTmpTable->markDir("/tmp/webacula/test/1/0 Каталог'tmp/", 0);
		// получаем суммарную статистику
    	$ares = $this->WbTmpTable->getTotalSummaryMark();
    	$this->assertTrue($ares['total_files'] == 0, __FUNCTION__." total files count = " . 
	    	$ares['total_files'] . " failed");
	}
	
	/**
    * @group restore
    */
	public function testCloneRecentBaculaToTmp() {
		print "\n".__METHOD__.' ';
		// запоминаем данные в сессии
		$this->restoreNamespace->typeRestore = 'restore_recent';
		$this->restoreNamespace->JobId = null;
		$this->restoreNamespace->ClientNameFrom = 'local.fd';
		$this->restoreNamespace->FileSet		= 'fileset.test.1';
		$this->restoreNamespace->DateBefore		= null;
		// поиск ClientId
		$client = new Client();
		$this->restoreNamespace->ClientIdFrom = $client->getClientId($this->restoreNamespace->ClientNameFrom);
		// поиск JobId
		$job = new Job();
		$ajobs = $job->getJobBeforeDate('', $this->restoreNamespace->ClientIdFrom, $this->restoreNamespace->FileSet);
		$this->assertTrue( isset($ajobs) , __FUNCTION__." No Full backup found");
   		// запоминаем данные о jobids в сессии 
    	$this->restoreNamespace->JobHash = md5($ajobs['hash']); 			
   		$this->restoreNamespace->aJobId  = $ajobs['ajob_all'];
   		$this->assertTrue( ( ($ajobs['ajob_all'][0] == 1) && ( $ajobs['ajob_all'][1] == 7) && 
   			(sizeof($ajobs['ajob_all']) == 2 )  ) ,	__FUNCTION__." 'Id=1  Full, Id=7  Diff' expected");
		$sjobids = implode(",", $this->restoreNamespace->aJobId);
		
		// собственно клонирование
		$this->WbTmpTableRecent = new WbTmpTable(self::_PREFIX, $this->restoreNamespace->JobHash);	
		$this->WbTmpTableRecent->cloneRecentBaculaToTmp($this->restoreNamespace->JobHash, $sjobids);
		
		// проверяем кол-во файлов и т.д.
		$res = $this->WbTmpTableRecent->getCountFile();
		$this->assertTrue($res == 3610, __FUNCTION__." failed (count files = $res)");
		$res = $this->WbTmpTableRecent->getCountFileName();
		$this->assertTrue($res == 3603, __FUNCTION__." failed (count file names = $res)");
		$res = $this->WbTmpTableRecent->getCountPath();
		$this->assertTrue($res == 8, __FUNCTION__." failed (count paths = $res)");
		// удаление временных таблиц
		$this->WbTmpTableRecent->deleteAllTmpTables();
		// проверяем удаление
		$this->assertFalse( $this->WbTmpTableRecent->isAllTmpTablesExists(), __FUNCTION__." temporary tables not deleted");
	}
	
	/**
    * @group restore
    */
	public function testCloneBeforeDateBaculaToTmp() {
		print "\n".__METHOD__.' ';
		// запоминаем данные в сессии
		$this->restoreNamespace->typeRestore = 'restore_recent';
		$this->restoreNamespace->JobId = null;
		$this->restoreNamespace->ClientNameFrom = 'local.fd';
		$this->restoreNamespace->FileSet		= 'fileset.test.1';
		$this->restoreNamespace->DateBefore		= date("Y-m-d H:i:s", time());
		// поиск ClientId
		$client = new Client();
		$this->restoreNamespace->ClientIdFrom = $client->getClientId($this->restoreNamespace->ClientNameFrom);
		// поиск JobId
		$job = new Job();
		$date_before = " AND Job.StartTime<'".$this->restoreNamespace->DateBefore."'";
		$ajobs = $job->getJobBeforeDate($date_before, 
			$this->restoreNamespace->ClientIdFrom, $this->restoreNamespace->FileSet);
		$this->assertTrue( isset($ajobs) , __FUNCTION__." No Full backup found");
   		// запоминаем данные о jobids в сессии 
    	$this->restoreNamespace->JobHash = md5($ajobs['hash']); 			
   		$this->restoreNamespace->aJobId  = $ajobs['ajob_all'];
   		$this->assertTrue( ( ($ajobs['ajob_all'][0] == 1) && ( $ajobs['ajob_all'][1] == 7) && 
   			(sizeof($ajobs['ajob_all']) == 2 )  ) ,	__FUNCTION__." 'Id=1  Full, Id=7  Diff' expected");
		$sjobids = implode(",", $this->restoreNamespace->aJobId);
		
		// собственно клонирование
		$this->WbTmpTableRecent = new WbTmpTable(self::_PREFIX, $this->restoreNamespace->JobHash);	
		$this->WbTmpTableRecent->cloneRecentBaculaToTmp($this->restoreNamespace->JobHash, $sjobids);
		
		// проверяем кол-во файлов и т.д.
		$res = $this->WbTmpTableRecent->getCountFile();
		$this->assertTrue($res == 3610, __FUNCTION__." failed (count files = $res)");
		$res = $this->WbTmpTableRecent->getCountFileName();
		$this->assertTrue($res == 3603, __FUNCTION__." failed (count file names = $res)");
		$res = $this->WbTmpTableRecent->getCountPath();
		$this->assertTrue($res == 8, __FUNCTION__." failed (count paths = $res)");
		// удаление временных таблиц
		$this->WbTmpTableRecent->deleteAllTmpTables();
		// проверяем удаление
		$this->assertFalse( $this->WbTmpTableRecent->isAllTmpTablesExists(), __FUNCTION__." temporary tables not deleted");
	}
	
	/**
	 * ВАЖНО: этот тест должен быть самым последним !!!
	 * @group restore
	 */
	public function testDeleteAllTmpTables() {
		print "\n".__METHOD__.' ';
		$this->WbTmpTable = new WbTmpTable(self::_PREFIX, md5($this->jobid));
		$this->WbTmpTable->deleteAllTmpTables();
		$this->assertTrue(TRUE);
	}
	
	
}