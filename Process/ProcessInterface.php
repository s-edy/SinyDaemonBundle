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

use Siny\DaemonBundle\Process\ForkableInterface;
use Siny\DaemonBundle\Process\WaitableInterface;

/**
 * This is an interface of process.
 *
 * @author Shinichiro Yuki <edy@siny.jp>
 */
interface ProcessInterface extends ForkableInterface, WaitableInterface
{
    /**
     * Register callback for handling signal
     *
     * @param integer $signal   A signal type
     * @param mixed   $callback Callback function name string or object method array.
     */
    public function registerSignal($signal, $callback);

    /**
     * Get Registered signals
     *
	 * Get signals that was registered to handle

	 * @return array
     */
    public function getRegisteredSignals();

    /**
     * Catchs signal ?
     *
     * @return boolean Whenter this process catched signal
     */
    public function catchesSignal();

    /**
     * Handle signal
     *
     * @param integer $signal
     */
    public function handleSignal($signal);
}
