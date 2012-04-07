<?php
/**
 * This file is a part of Siny\DaemonBundle package.
*
* (c) Shinichiro Yuki <edy@siny.jp>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Siny\DaemonBundle\Tests\Daemon\Worker;

use Siny\DaemonBundle\Process\Daemon\Worker\Worker;
use \Monolog\Logger;

class WorkerTest extends \PHPUnit_Framework_TestCase
{
    const CLASS_NAME = "Siny\DaemonBundle\Process\Daemon\Worker\Worker";
    private $worker;

    public function setUp()
    {
        $this->worker = $this->getMockForAbstractClass(self::CLASS_NAME);
    }

    public function testSelfObjectWillReturnWhenInvokingSetLogger()
    {
        $logger = $this->getMock("Monolog\Logger", array(), array('name'));
        $this->assertSame($this->worker, $this->worker->setLogger($logger), "Self object wasn't return.");
    }

    public function testGetLoggerWillReturnWhenInvokingGetLogger()
    {
        $logger = $this->getMock("Monolog\Logger", array(), array('name'));
        $this->worker->setLogger($logger);
        $this->assertSame($logger, $this->worker->getLogger(), "Logger wasn't return");
    }

    public function testGetRegistrationSignals()
    {
        $this->setCallbackWithSighup();
        $this->assertSame(array(SIGHUP), $this->worker->getRegistrationSignals(), "Signals wasn't registered.");
    }

    public function testSelfObjectWillReturnWhenInvokingSetCallback()
    {
        $this->assertSame($this->worker, $this->setCallbackWithSighup(), "Self object wasn't return");
    }

    public function testHasCallback()
    {
        $this->setCallbackWithSighup();
        $this->assertTrue($this->worker->hasCallback(SIGHUP), "SIGHUP signal wasn't registered.");
        $this->assertFalse($this->worker->hasCallback(SIGINT), "SIGINT signal was registered.");
    }

    public function testGetCallback()
    {
        $this->setCallbackWithSighup();
        $this->assertSame(SIG_DFL, $this->worker->getCallback(SIGHUP), "SIG_DFL wasn't registered.");
        $this->assertNull($this->worker->getCallback(SIGINT), "Not returned null.");
    }

    private function setCallbackWithSighup()
    {
        $reflection = new \ReflectionMethod(self::CLASS_NAME, "setCallback");
        $reflection->setAccessible(true);
        return $reflection->invoke($this->worker, SIGHUP, SIG_DFL);
    }
}
