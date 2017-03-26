<?php
/**
 * Created by IntelliJ IDEA.
 * User: chuxiaofeng
 * Date: 17/1/31
 * Time: 下午1:41
 */

namespace Minimalism\A\Core;

/**
 * CPS: Interface Async
 * @package Minimalism\A\Core
 *
 * TODO 将begin修改为__invoke, 让Async满足is_callable
 */
interface Async
{
    /**
     * 开启异步任务，立即返回，任务完成回调$continuation
     * @param callable $continuation
     *      void(mixed $result = null, \Throwable|\Exception $ex = null)
     * @return void
     */
    public function begin(callable $continuation);
}