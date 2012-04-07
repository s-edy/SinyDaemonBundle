<?php
/**
 * This file is a part of Siny\DaemonBundle package.
*
* (c) Shinichiro Yuki <edy@siny.jp>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Siny\DaemonBundle\Tests\Daemon;

use Siny\DaemonBundle\Process\Daemon\Daemon;

class DaemonTest extends \PHPUnit_Framework_TestCase
{
    private $pwd;
    private $umask;
    private $pid;
    private $executed = false;
    private $worker;
    private $daemon;

    public static function setUpBeforeClass()
    {
        self::_unlink(self::_getSQLiteFilePath());
        self::_setupSqlite();
    }

    public static function tearDownAfterClass()
    {
        self::_unlink(self::_getSQLiteFilePath());
    }

    public function setUp()
    {
        $this->pwd   = getcwd();
        $this->umask = umask();
        $this->flush();
        $this->set('exec', 0);
        $this->pid = posix_getpid();
        $this->worker = $this->getMock("Siny\DaemonBundle\Process\Daemon\Worker\WorkableInterface");
        $this->worker->expects($this->any())->method("getRegistrationSignals")->will($this->returnValue(array(SIGHUP)));
        $this->worker->expects($this->any())->method("hasCallback")->will($this->returnValue(true));
        $this->worker->expects($this->any())->method("getCallback")->will($this->returnValue(SIG_DFL));
        $this->daemon = new Daemon($this->worker);
        $this->clearExit();
    }

    public function tearDown()
    {
        chdir($this->pwd);
        umask($this->umask);
    }

    public function testGetWorkerInTheCaseOfDefault()
    {
        $this->assertSame($this->worker, $this->daemon->getWorker(), "Worker wasn't same.");
    }

    public function testIsDaemonInTheCaseOfDefault()
    {
        $this->assertFalse($this->daemon->isDaemon(), "This process was already daemonized.");
    }

    public function testGetRunningDirectoryInTheCaseOfDefault()
    {
        $this->assertSame("/", $this->daemon->getRunningDirectory()->getRealPath(), "Default running directory wasn't root.");
    }

    public function testSelfObjectWillReturnWhenInvokingSetRunningDirectory()
    {
        $dir = new \SplFileInfo(__DIR__);
        $this->assertSame($this->daemon, $this->daemon->setRunningDirectory($dir), "Self object didn't return.");
    }

    public function testGetWorker()
    {
        $this->assertInstanceOf('Siny\DaemonBundle\Process\Daemon\Worker\WorkableInterface', $this->daemon->getWorker(), "Not WorkableInterface");
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionWillReturnWhenInvokingSetRunningDirectoryInTheCaseOfNotDirectory()
    {
        $this->daemon->setRunningDirectory(new \SplFileInfo(__FILE__));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionWillReturnWhenInvokingSetRunningDirectoryInTheCaseOfNotReadable()
    {
        $dir = $this->getMock("\SplFileInfo", array('isReadable'), array(DIRECTORY_SEPARATOR));
        $dir->expects($this->once())
            ->method('isReadable')
            ->will($this->returnValue(false));
        $this->daemon->setRunningDirectory($dir);
    }

    public function testDispatchSignal()
    {
        $this->assertInternalType('boolean', $this->daemon->dispatchSignal(), "Could not dispatch.");
    }

    public function testNeverRunningWhenInvokingStartWithoutEnteringDaemonMode()
    {
        $this->worker->expects($this->never())->method('start');
        $daemon = $this->getMock("Siny\DaemonBundle\Process\Daemon\Daemon", array("daemonize"), array($this->worker));
        $daemon->expects($this->once())->method('daemonize');
        $daemon->expects($this->never())->method('run');
        $daemon->start();
    }

    public function testRunningWhenInvokingStartWithEnteringDaemonMode()
    {
        $this->worker->expects($this->once())->method('start');
        $daemon = $this->getMock("Siny\DaemonBundle\Process\Daemon\Daemon", array("daemonize", "isDaemon", "run"), array($this->worker));
        $daemon->expects($this->once())->method('daemonize');
        $daemon->expects($this->once())->method('isDaemon')->will($this->returnValue(true));
        $daemon->expects($this->once())->method('run');
        $daemon->start();
    }

    public function testWorkerWillStopWhenInvokingStop()
    {
        $this->worker->expects($this->once())->method('stop');
        $daemon = new Daemon($this->worker);
        $daemon->stop();
    }

    /**
     * Parent process will not be changed its own PID after daemonized.
     */
    public function testParentProcessWillNotBeChangedItsOwnPidAfterDaemonized()
    {
        $pid = $this->daemon->fork();
        if ($this->daemon->isParentProcess()) {
            $this->waitAllProcess();
            $this->assertSame('true', $this->get(__METHOD__ . 'isParentProcess'), "This is not 1st forked process.");
            $this->assertSame('false', $this->get(__METHOD__ . 'isChildProcess'), "This is not 1st forked process.");
            $this->assertSame('false', $this->get(__METHOD__ . 'isDaemon'), "This is not 1st forked process.");
            $this->executed = true;
        } else {
            $before = posix_getpid();
            $this->daemon->daemonize();
            if ($before === posix_getpid()) {
                $this->set(__METHOD__ . 'isParentProcess', $this->daemon->isParentProcess() ? 'true' : 'false');
                $this->set(__METHOD__ . 'isChildProcess', $this->daemon->isChildProcess() ? 'true' : 'false');
                $this->set(__METHOD__ . 'isDaemon', $this->daemon->isDaemon() ? 'true' : 'false');
            }
            $this->incrementExit();
        }
        $this->checkExecute();
    }

    /**
     * The child process
     */
    public function testDaemonize2ndForkInTheCaseOfChild()
    {
        $pid = $this->daemon->fork();
        if ($this->daemon->isParentProcess()) {
            $this->waitAllProcess();
            $this->assertSame('false', $this->get(__METHOD__ . 'isParentProcess'), "This is not 2nd forked process.");
            $this->assertSame('false', $this->get(__METHOD__ . 'isDaemon'), "This is not 2nd forked process.");
            $this->executed = true;
        } else {
            $ret = $this->daemon->daemonize();
            if ($this->daemon->isParentProcess()) {
                $this->set(__METHOD__ . '1st', posix_getpid());
            } else if ($this->daemon->isChildProcess()) {
                $this->set(__METHOD__ . 'isParentProcess', $this->daemon->isParentProcess() ? 'true' : 'false');
                $this->set(__METHOD__ . 'isDaemon', $this->daemon->isDaemon() ? 'true' : 'false');
            }
            $this->incrementExit();
        }
        $this->checkExecute();
    }

    public function testDaemonize2ndForkInTheCaseOfSeparateSession()
    {
        $pid = $this->daemon->fork();
        if ($this->daemon->isParentProcess()) {
            $this->waitAllProcess();
            $this->assertSame(posix_getsid(0), $this->get(__METHOD__ . '1st'), "before sid wasn't same.");
            $this->assertGreaterThan(1, $this->get(__METHOD__ . '2nd'), "after sid wasn't set");
            $this->assertNotSame($this->get(__METHOD__ . '1st'), $this->get(__METHOD__ . '2nd'), "Failed to separate session.");
            $this->executed = true;
        } else {
            $ret = $this->daemon->daemonize();
            if ($this->daemon->isParentProcess()) {
                $this->set(__METHOD__ . '1st', posix_getsid(0));
            } else if ($this->daemon->isChildProcess()) {
                $this->set(__METHOD__ . '2nd', posix_getsid(0));
            }
            $this->incrementExit();
        }
        $this->checkExecute();
    }

    public function testDaemonize3rdForkInTheCaseOfGrandchild()
    {
        $pid = $this->daemon->fork();
        if ($this->daemon->isParentProcess()) {
            $this->waitAllProcess();
            $this->assertSame('false', $this->get(__METHOD__ . 'isParentProcess'), "This is not 3rd forked process.");
            $this->assertSame('false', $this->get(__METHOD__ . 'isChildProcess'), "This is not 3rd forked process.");
            $this->assertSame('true', $this->get(__METHOD__ . 'isDaemon'), "This is not 3rd forked process.");
            $this->executed = true;
        } else {
            $ret = $this->daemon->daemonize();
            if (! $this->daemon->isParentProcess() && ! $this->daemon->isChildProcess()) {
                $this->set(__METHOD__ . 'isParentProcess', $this->daemon->isParentProcess() ? 'true' : 'false');
                $this->set(__METHOD__ . 'isChildProcess', $this->daemon->isChildProcess() ? 'true' : 'false');
                $this->set(__METHOD__ . 'isDaemon', $this->daemon->isDaemon() ? 'true' : 'false');
            }
            $this->incrementExit();
        }
        $this->checkExecute();
    }

    public function testDaemonize3rdForkInTheCaseOfZombie()
    {
        $pid = $this->daemon->fork();
        if ($this->daemon->isParentProcess()) {
            $this->waitAllProcess();
            $this->assertSame(1, $this->get(__METHOD__), "Failed to be a zombie process.");
            $this->executed = true;
        } else {
            $ret = $this->daemon->daemonize();
            if ($this->daemon->isDaemon()) {
                $this->waitToBeAZombie();
                $this->set(__METHOD__, posix_getppid());
            }
            $this->incrementExit();
        }
        $this->checkExecute();
    }

    public function testDaemonize3rdForkInTheCaseOfSeparateSession()
    {
        $pid = $this->daemon->fork();
        if ($this->daemon->isParentProcess()) {
            $this->waitAllProcess();
            $this->assertSame(posix_getsid(0), $this->get(__METHOD__ . '1st-sid'), "before sid wasn't same.");
            $this->assertGreaterThan(1, $this->get(__METHOD__ . '3rd-sid'), "after sid wasn't set");
            $this->assertNotSame(posix_getsid(0), $this->get(__METHOD__ . '3rd-sid'), "Failed to separate session.");
            $this->executed = true;
        } else {
            $ret = $this->daemon->daemonize();
            if ($this->daemon->isParentProcess()) {
                $this->set(__METHOD__ . '1st-sid', posix_getsid(0));
            } else if ($this->daemon->isDaemon()) {
                $this->waitToBeAZombie();
                $this->set(__METHOD__ . '3rd-sid', posix_getsid(0));
            }
            $this->incrementExit();
        }
        $this->checkExecute();
    }

    public function testNotChangeCurrentDirectoryWhenInvokingDaemonize1stFork()
    {
        $dir = getcwd();
        $umask = umask();

        $daemon = $this->getMock("Siny\DaemonBundle\Process\Daemon\Daemon", array("fork", "isParentProcess"), array($this->worker));
        $daemon->expects($this->once())->method("fork");
        $daemon->expects($this->once())->method("isParentProcess")->will($this->returnValue(true));
        $daemon->daemonize();

        $this->assertSame($dir, getcwd(), "Did not change current directory.");
        $this->assertFalse($daemon->isDaemon(), "Daemon process.");
        $this->assertSame($umask, umask(), "Umask was changed.");
    }

    public function testNotChangeCurrentDirectoryWhenInvokingDaemonize2ndFork()
    {
        $dir = getcwd();
        $umask = umask();

        $daemon = $this->getMock("Siny\DaemonBundle\Process\Daemon\Daemon", array("fork", "isParentProcess", "setSid"), array($this->worker));
        $daemon->expects($this->any())->method("fork");
        $daemon->expects($this->once())->method("setSid");
        $daemon->expects($this->any())->method("isParentProcess")->will($this->onConsecutiveCalls(false, true));
        $daemon->daemonize();

        $this->assertSame($dir, getcwd(), "Did not change current directory.");
        $this->assertTrue($daemon->isChildProcess(), "Not child process.");
        $this->assertFalse($daemon->isDaemon(), "Daemon process.");
        $this->assertSame($umask, umask(), "Umask was changed.");
    }

    public function testNotChangeCurrentDirectoryWhenInvokingDaemonize3rdFork()
    {
        $dir = getcwd();
        $umask = umask();

        $daemon = $this->getMock("Siny\DaemonBundle\Process\Daemon\Daemon", array("fork", "isParentProcess", "setSid", "fclose"), array($this->worker));
        $daemon->expects($this->any())->method("fork");
        $daemon->expects($this->once())->method("setSid");
        $daemon->expects($this->any())->method("isParentProcess")->will($this->returnValue(false));
        $daemon->expects($this->any())->method("fclose");
        $daemon->daemonize();

        $this->assertNotSame(getcwd(), $dir, "Did not change current directory.");
        $this->assertSame(getcwd(), $daemon->getRunningDirectory()->getRealPath(), "Did not change current directory.");
        $this->assertFalse($daemon->isChildProcess(), "This is child process.");
        $this->assertTrue($daemon->isDaemon(), "Not daemon process.");
        $this->assertSame(0, umask(), "Umask wasn't changed.");
    }

    /**
     * @expectedException Siny\DaemonBundle\Process\Daemon\Exception\DaemonException
     */
    public function testExceptionWillOccurWhenFailingSetSidWhenInvokingDaemonize()
    {
        $daemon = $this->getMock("Siny\DaemonBundle\Process\Daemon\Daemon", array("fork", "isParentProcess", "doSetSid"), array($this->worker));
        $daemon->expects($this->once())->method("fork");
        $daemon->expects($this->once())->method("isParentProcess")->will($this->returnValue(false));
        $daemon->expects($this->once())->method("doSetSid")->will($this->returnValue(-1));
        $daemon->daemonize();
    }

    /**
     * @expectedException Siny\DaemonBundle\Process\Daemon\Exception\DaemonException
     */
    public function testExceptionWillOccurWhenFailingChdirWhenInvokingDaemonize()
    {
        $daemon = $this->getMock("Siny\DaemonBundle\Process\Daemon\Daemon", array("fork", "isParentProcess", "setSid", "doChdir"), array($this->worker));
        $daemon->expects($this->any())->method("fork");
        $daemon->expects($this->any())->method("isParentProcess")->will($this->returnValue(false));
        $daemon->expects($this->once())->method("setSid");
        $daemon->expects($this->once())->method("doChdir")->will($this->returnValue(false));
        $daemon->daemonize();
    }

    /**
     * @expectedException Siny\DaemonBundle\Process\Daemon\Exception\DaemonException
     */
    public function testExceptionWillOccurWhenFailingUmaskWhenInvokingDaemonize()
    {
        $daemon = $this->getMock("Siny\DaemonBundle\Process\Daemon\Daemon", array("fork", "isParentProcess", "setSid", "chdir", "doUmask"), array($this->worker));
        $daemon->expects($this->any())->method("fork");
        $daemon->expects($this->any())->method("isParentProcess")->will($this->returnValue(false));
        $daemon->expects($this->once())->method("setSid");
        $daemon->expects($this->once())->method("chdir");
        $daemon->expects($this->any())->method("doUmask")->will($this->returnValue(0644));
        $daemon->daemonize();
    }

    /**
     * @expectedException Siny\DaemonBundle\Process\Daemon\Exception\DaemonException
     */
    public function testExceptionWillOccurWhenFailingFcloseWhenInvokingDaemonize()
    {
        $daemon = $this->getMock("Siny\DaemonBundle\Process\Daemon\Daemon", array("fork", "isParentProcess", "setSid", "chdir", "umask", "doFclose"), array($this->worker));
        $daemon->expects($this->any())->method("fork");
        $daemon->expects($this->any())->method("isParentProcess")->will($this->returnValue(false));
        $daemon->expects($this->once())->method("setSid");
        $daemon->expects($this->once())->method("chdir");
        $daemon->expects($this->any())->method("umask");
        $daemon->expects($this->once())->method("doFclose")->will($this->returnValue(false));
        $daemon->daemonize();
    }

    public function testRunInfinitelyUntilSignalDipatch()
    {
        $this->worker->expects($this->exactly(3))->method('work');
        $daemon = $this->getMock("Siny\DaemonBundle\Process\Daemon\Daemon", array("catchesSignal", "dispatchSignal"), array($this->worker));
        $daemon->expects($this->exactly(4))->method('catchesSignal')->will($this->onConsecutiveCalls(false, false, false, true));
        $daemon->expects($this->exactly(3))->method('dispatchSignal');
        $daemon->run();
    }

    private static function _setupSqlite()
    {
        self::_getPDO()->exec('CREATE TABLE DaemonTest(key STRING, value STRING)');
    }

    private static function _getSQLiteFilePath()
    {
        return realpath(__DIR__ . '/../../../tmp') . '/DaemonTest.sq3';
    }

    private static function _getPDO()
    {
        $sqliteFile = self::_getSQLiteFilePath();
        return new \PDO(sprintf('sqlite:%s', $sqliteFile), null, null);
    }

    private static function _unlink($file)
    {
        if (file_exists($file)) {
            if (! unlink($file)) {
                throw new RuntimeException(sprintf('Failed to unlink. path=[%s].', $file));
            }
        }
    }

    private function set($key, $value)
    {
        $pdo = self::_getPDO();
        $pdo->exec('BEGIN EXCLUSIVE');
        $pdo->exec(sprintf("INSERT INTO DaemonTest VALUES ('%s', '%s')", $key, $value));
        $pdo->exec('COMMIT');
    }

    private function get($key)
    {
        $statement = self::_getPDO()->query(sprintf("SELECT value FROM DaemonTest WHERE key = '%s'", $key));
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        return ctype_digit($row['value']) ? intval($row['value']) : $row['value'];
    }

    private function flush()
    {
        $pdo = self::_getPDO();
        $pdo->exec('DROP TABLE DaemonTest');
        $pdo->exec('CREATE TABLE DaemonTest(key STRING, value STRING)');
    }

    private function checkExecute()
    {
        $this->assertTrue($this->executed, "Did not execute.");
    }

    private function waitAllProcess()
    {
        $this->daemon->waitPID();
        $lockfile = $this->getLockFile();
        $fp = fopen($lockfile, 'r');
        while (true) {
            fseek($fp, 0);
            $value = trim(fread($fp, 1024));
            if ($value >= 3) {
                break;
            }
            usleep(5);
        };
        fclose($fp);
    }

    private function waitToBeAZombie()
    {
        $start = time();
        while ($start > time() - 5) {
            if (posix_getppid() === 1) {
                return;
            }
            usleep(10);
        };
    }

    private function clearExit()
    {
        $lockfile = $this->getLockFile();
        $fp = fopen($lockfile, 'w');
        fclose($fp);
    }

    private function incrementExit()
    {
        $lockfile = $this->getLockFile();
        $fp = fopen($lockfile, 'a+');
        while (! flock($fp, LOCK_EX)) {
            usleep(mt_rand(1, 10));
        }
        fseek($fp, 0);
        $value = trim(fread($fp, 1024));
        ftruncate($fp, 0);
        fwrite($fp, $value + 1);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        exit();
    }

    private function getLockFile()
    {
        return __DIR__ . '/../../../tmp/lock';
    }
}
