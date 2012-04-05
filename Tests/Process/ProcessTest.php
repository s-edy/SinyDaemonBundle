<?php
/**
 * This file is a part of Siny\DaemonBundle package.
*
* (c) Shinichiro Yuki <edy@siny.jp>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Siny\DaemonBundle\Tests\Process;

use Siny\DaemonBundle\Process\Process;

class ProcessTest extends \PHPUnit_Framework_TestCase
{
    private $pid;
    private static $pwd;
    private static $umask;
    private static $_pdo;
    private $executed = false;
    private $process;

    public static function setUpBeforeClass()
    {
        self::$pwd   = getcwd();
        self::$umask = umask();
        self::_unlink(self::_getSQLiteFilePath());
        self::_setupSqlite();
    }

    public static function tearDownAfterClass()
    {
        self::_unlink(self::_getSQLiteFilePath());
    }

    public function setUp()
    {
        pcntl_signal(SIGHUP, SIG_DFL);
        $this->flush();
        $this->executed = false;
        $this->pid = posix_getpid();
        chdir(self::$pwd);
        umask(self::$umask);
        $this->process = new Process();
    }

    public function tearDown()
    {
        if ($this->isChildProcess()) {
            exit();
        }
    }

    public function testCatchesSignalInTheCaseOfDefault()
    {
        $this->assertFalse($this->process->catchesSignal(), "Any signal wasn't caught.");
    }

    public function testIsForkedInTheCaseOfDefault()
    {
        $this->assertFalse($this->process->isForked(), "Already forked in the case of default.");
    }

    public function testIsChildInTheCaseOfDefault()
    {
        $this->assertFalse($this->process->isChildProcess(), "This process is child in the case of default.");
    }

    public function testIsParentInTheCaseOfDefault()
    {
        $this->assertTrue($this->process->isParentProcess(), "This process is parent in the case of default.");
    }

    /**
     * @dataProvider provideSignalHandleConstants
     */
    public function testRegisterSignalInTheCaseOfConstants($callback)
    {
        $this->process->registerSignal(SIGHUP, $callback);
        $signals = $this->process->getRegisteredSignals();
        $this->assertFalse(isset($signals[SIGHUP]), 'SIG_IGN and SIG_DFL cannot register');
    }

    public function provideSignalHandleConstants()
    {
        return array(
            array(SIG_IGN),
            array(SIG_DFL),
        );
    }

    public function testRegisterSignalInTheCaseOfAnyCallback()
    {
        $callback = array($this, "callbackSet");
        $this->process->registerSignal(SIGHUP, $callback);
        $signals = $this->process->getRegisteredSignals();
        $this->assertSame($callback, $signals[SIGHUP], "Callback object wasn't same.");
    }

    /**
     * @expectedException Siny\DaemonBundle\Process\Exception\ProcessException
     */
    public function testExceptionWillOccurWhenInvokingRegisterSignalInTheCaseOfNotCallableCallback()
    {
        $this->process->registerSignal(SIGHUP, 'NotCallable');
    }

    /**
     * @expectedException Siny\DaemonBundle\Process\Exception\ProcessException
     */
    public function testExceptionWillOccurWhenInvokingRegisterSignalInTheCaseOfFailedToRegisterSignal()
    {
        $process = $this->getMock("Siny\DaemonBundle\Process\Process", array("doSignal"));
        $process->expects($this->any())
            ->method("doSignal")
            ->will($this->returnValue(false));
        $process->registerSignal(SIGHUP, array($this, "callbackSet"));
    }

    public function testHandleSignalInTheCaseOfSpecificCallback()
    {
        $this->process->registerSignal(SIGHUP, array($this, "callbackSet"));
        posix_kill(posix_getpid(), SIGHUP);
        pcntl_signal_dispatch();
        $this->assertSame($this->get(), SIGHUP, "Callback execute unsuccessfully");
    }

    public function callbackSet($signal)
    {
        $this->set($signal);
    }

    public function testIsForkedInTheCaseOfParentProcess()
    {
        $pid = $this->process->fork();
        if ($this->isParentProcess()) {
            $this->waitpid($pid);
            $this->assertTrue($this->process->isForked(), "Parent process is NOT forked.");
        }
        $this->checkExecute();
    }

    public function testIsForkedInTheCaseOfChildProcess()
    {
        $pid = $this->process->fork();
        if ($this->isParentProcess()) {
            $this->waitpid($pid);
            $this->assertSame($this->get(), 'Yes, forked', "Child process is NOT forked.");
        } else {
            $this->set($this->process->isForked() ? 'Yes, forked' : 'No, not forked.');
        }
        $this->checkExecute();
    }

    public function testIsParentInTheCaseOfParentProcess()
    {
        $pid = $this->process->fork();
        if ($this->isParentProcess()) {
            $this->waitpid($pid);
            $this->assertTrue($this->process->isParentProcess(), "This is NOT parent process.");
        }
        $this->checkExecute();
    }

    public function testIsChildInTheCaseOfParentProcess()
    {
        $pid = $this->process->fork();
        if ($this->isParentProcess()) {
            $this->waitpid($pid);
            $this->assertFalse($this->process->isChildProcess(), "This is child process.");
        }
        $this->checkExecute();
    }

    public function testIsParentInTheCaseOfChildProcess()
    {
        $pid = $this->process->fork();
        if ($this->isParentProcess()) {
            $this->waitpid($pid);
            $this->assertSame("I am not parent", $this->get(), "This is parent process");
        } else {
            $this->set($this->process->isParentProcess() ? 'I am parent' : 'I am not parent');
        }
        $this->checkExecute();
    }

    public function testIsChildInTheCaseOfChildProcess()
    {
        $pid = $this->process->fork();
        if ($this->isParentProcess()) {
            $this->waitpid($pid);
            $this->assertSame("I am child", $this->get(), "This is NOT child process");
        } else {
            $this->set($this->process->isChildProcess()  ? 'I am child'  : 'I am not child');
        }
        $this->checkExecute();
    }

    public function testPositivePidNumberWillReturnWhenInvokingForkInTheCaseOfParentProcess()
    {
        $pid = $this->process->fork();
        if ($this->isParentProcess()) {
            $this->waitpid($pid);
            $this->assertGreaterThan(0, $pid, "positive integer will return when invoking fork() in the case of parent process");
        }
        $this->checkExecute();
    }

    public function testZeroWillReturnWhenInvokingForkInTheCaseOfChildProcess()
    {
        $pid = $this->process->fork();
        if ($this->isParentProcess()) {
            $this->waitpid($pid);
            $this->assertSame(0, $this->get(), "zero integer will return when invoking fork() in the case of forked child process");
        } else {
            $this->set($pid);
        }
        $this->checkExecute();
    }

    /**
     * @expectedException Siny\DaemonBundle\Process\Exception\ForkException
     */
    public function testExceptionWillOccurWhenInvokingForkFailed()
    {
        $process = $this->getMock("Siny\DaemonBundle\Process\Process", array("doFork"));
        $process->expects($this->once())
            ->method("doFork")
            ->will($this->returnValue(-1));
        $process->fork();
    }

    public function testExitedPidWillReturnWhenInvokingWaitPid()
    {
        $pid = pcntl_fork();
        if ($pid > 0) {
            $this->assertSame($pid, $this->process->waitPID(), "Exited PID will return when invoking waitPID() in the case of parent process");
            $this->executed = true;
        }
        $this->checkExecute();
    }

    public function testZeroWillReturnWhenInvokingWaitPidWithWnohangOption()
    {
        $pid = pcntl_fork();
        if ($pid > 0) {
            $this->assertSame(0, $this->process->waitPID(-1, WNOHANG), "Exited PID will return when invoking waitPID() in the case of parent process");
            $this->executed = true;
        } else {
            usleep(1000);
        }
        $this->checkExecute();
    }

    /**
     * @expectedException Siny\DaemonBundle\Process\Exception\WaitException
     */
    public function testExceptionWillOccurWhenInvokingWaitFailed()
    {
        $process = $this->getMock("Siny\DaemonBundle\Process\Process", array("doWaitPID"));
        $process->expects($this->once())
            ->method("doWaitPID")
            ->will($this->returnValue(-1));
        $process->waitPID();
    }

    public function testCurrentSessionSidWillReturnWhenInvokingSetSid()
    {
        $expects = 12345;
        $process = $this->getMock("Siny\DaemonBundle\Process\Process", array("doSetSid"));
        $process->expects($this->once())->method('doSetSid')->will($this->returnValue($expects));
        $this->assertSame($expects, $process->setSid(), "Returned session id wasn't same.");
    }

    /**
     * @expectedException Siny\DaemonBundle\Process\Exception\ProcessException
     */
    public function testExceptionWillOccurWhenInvokingSetSidOnProcessLeader()
    {
        $process = $this->getMock("Siny\DaemonBundle\Process\Process", array("doSetSid"));
        $process->expects($this->once())->method('doSetSid')->will($this->returnValue(-1));
        $process->setSid();
    }

    public function testChdir()
    {
        $target = (self::$pwd === DIRECTORY_SEPARATOR) ? __DIR__ : DIRECTORY_SEPARATOR;
        $directory = new \SplFileInfo($target);
        $this->assertNull($this->process->chdir($directory), "Change directory didn't succeed.");
        $this->assertSame($directory->getRealPath(), getcwd(), "Current directory wasn't changed.");
        $this->assertNotSame(self::$pwd, getcwd(), "Current directory wasn't changed.");
    }

    /**
     * @expectedException Siny\DaemonBundle\Process\Exception\ProcessException
     */
    public function testExceptionWillOccurWhenNotDirectoryInvokingChdir()
    {
        $directory = $this->getMock("\SplFileInfo", array("isDir"), array(__DIR__));
        $directory->expects($this->once())->method("isDir")->will($this->returnValue(false));
        $this->process->chdir($directory);
    }

    /**
     * @expectedException Siny\DaemonBundle\Process\Exception\ProcessException
     */
    public function testExceptionWillOccurWhenChangingToUnpermittedDirectoryWhenInvokingChdir()
    {
        $directory = $this->getMock("\SplFileInfo", array("isReadable"), array(__DIR__));
        $directory->expects($this->once())->method("isReadable")->will($this->returnValue(false));
        $this->process->chdir($directory);
    }

    /**
     * @expectedException Siny\DaemonBundle\Process\Exception\ProcessException
     */
    public function testExceptionWillOccurWhenChangingToDirectoryWasFailedWhenInvokingChdir()
    {
        $process = $this->getMock("Siny\DaemonBundle\Process\Process", array("doChdir"));
        $process->expects($this->once())->method('doChdir')->will($this->returnValue(false));
        $process->chdir(new \SplFileInfo(__DIR__));
    }

    public function testUmaskValueBeforeSetWasNotReturn()
    {
        $this->assertSame(self::$umask, $this->process->umask(0), "Umask value before set wasn't return.");
        $this->assertSame(0, umask(), "Current umask wasn't changed.");
    }

    public function testCurrentUmaskValueWillReturnAsNoArguments()
    {
        $this->assertSame(self::$umask, $this->process->umask(), "Current umask value wasn't return.");
        $this->assertSame(self::$umask, umask(), "Current umask was changed.");
    }

    /**
     * @expectedException Siny\DaemonBundle\Process\Exception\ProcessException
     */
    public function testExceptionWillReturnWhenNotChangeUmaskValueWhenInvokingUmask()
    {
        $process = $this->getMock("Siny\DaemonBundle\Process\Process", array("doUmask"));
        $process->expects($this->any())->method('doUmask')->will($this->onConsecutiveCalls(0644, 0644));
        $process->umask(0644);
    }

    public function testFclose()
    {
        $descriptor = fopen("php://memory", "w");
        $this->assertNull($this->process->fclose($descriptor));
        $this->assertFalse(is_resource($descriptor), "File descriptor wasn't closed.");
    }

    /**
     * @expectedException Siny\DaemonBundle\Process\Exception\ProcessException
     */
    public function testExceptionWillReturnWhenInvokingFclose()
    {
        $process = $this->getMock("Siny\DaemonBundle\Process\Process", array("doFclose"));
        $process->expects($this->any())->method('doFclose')->will($this->returnValue(false));
        $process->fclose(fopen("php://memory", "w"));
    }

    private static function _setupSqlite()
    {
        $sqliteFile = self::_getSQLiteFilePath();
        self::$_pdo = new \PDO(sprintf('sqlite:%s', $sqliteFile), null, null);
        self::$_pdo->exec('CREATE TABLE ProcessTest(value STRING)');
    }

    private static function _getSQLiteFilePath()
    {
        return realpath(__DIR__ . '/../../tmp') . '/ProcessTest.sq3';
    }

    private static function _unlink($file)
    {
        if (file_exists($file)) {
            if (! unlink($file)) {
                throw new RuntimeException(sprintf('Failed to unlink. path=[%s].', $file));
            }
        }
    }

    private function set($contents)
    {
        self::$_pdo->exec(sprintf("INSERT INTO ProcessTest VALUES ('%s')", $contents));
    }

    private function get()
    {
        $statement = self::$_pdo->query('SELECT value FROM ProcessTest LIMIT 1');
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        return ctype_digit($row['value']) ? intval($row['value']) : $row['value'];
    }

    private function flush()
    {
        self::$_pdo->exec('DROP TABLE ProcessTest');
        self::$_pdo->exec('CREATE TABLE ProcessTest(value STRING)');
    }

    private function isParentProcess()
    {
        return ($this->pid === posix_getpid());
    }

    private function isChildProcess()
    {
        return ($this->isParentProcess() === false);
    }

    private function waitpid($pid)
    {
        pcntl_waitpid($pid, $status);
        $this->executed = true;
    }

    private function checkExecute()
    {
        $this->assertTrue($this->executed, "Did not execute.");
    }
}
