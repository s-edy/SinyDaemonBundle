<?php
/**
 * This file is a part of Siny\DaemonBundle package.
 *
 * (c) Shinichiro Yuki <edy@siny.jp>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Siny\DaemonBundle\Process;

/**
 * This is an waitable interface.
 *
 * @author Shinichiro Yuki <edy@siny.jp>
 */
interface WaitableInterface
{
    /**
     * Wait
     *
     * @param integer $pid    The PID to wait
     * @param integer $option 0, WNOHANG, WUNTRACED
     *
     * @return integer - the PID of the exited child process
     * @throws Siny\DaemonBundle\Process\Exception\WaitException
     */
    public function waitPID($pid = -1, $option = 0);
}
