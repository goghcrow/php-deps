<?php
/**
 * Created by IntelliJ IDEA.
 * User: chuxiaofeng
 * Date: 17/1/30
 * Time: 上午12:29
 */

namespace Minimalism\A\Core;
use Minimalism\A\Core\Exception\CancelTaskException;


/**
 * Class AsyncTask
 * yield实现的半协程递归调度器
 *
 * @author xiaofeng
 *
 * 异步任务辅助类:
 * 通过yield实现控制流操控, 同步语法解糖(CPS转换?!)
 * 展开yield, 手动迭代\Generator, 通过$continuation传递异步任务结果
 *
 *
 * 说明:
 * 1. 可以通过then回调取回任务最终结果(result, exception)
 *    如果不想通过continuation回调获取result与exception, 可以多嵌套一层, 作为子任务运行,
 *    在父任务try catch异常, 获取结果, 忽略父任务continuation回调
 * 2. 不会因为exception导致fatal error，(swoole异步回调的内的异常必须捕获, 否则Fatal Error)， 通过then回调continuation取回异常
 * 2. continuation 中不能抛出异常 !!!
 * 3. 抛出 CancelTaskException 及其子类, 不在任务间透传, 直接终止异步任务(停止迭代), 执行continuation回调
 * 4. 抛出 其他异常 内部不捕获, 任务会终止, 异常通过continuation回调参数传递
 * 5. 抛出 其他异常 内部捕获, 任务继续执行
 * 6. IAsync 实现类内部通过回调函数参数传递执行结果与异常
 * 7. 递归实现, 避免在大循环内部同步yield, 会占用大量内存, 如果有Async返回则没问题, 回调函数之前会退栈
 */
final class AsyncTask implements Async
{
    private $isfirst = true;

    public $parent;
    public $generator;
    public $continuation;

    /**
     * AsyncTask constructor.
     * @param \Generator $generator
     * @param AsyncTask|null $parent
     */
    public function __construct(\Generator $generator, AsyncTask $parent = null)
    {
        $this->generator = $generator;
        $this->parent = $parent;
    }

    /**
     * @param callable|null $continuation function($r, $ex = null) { }
     */
    public function begin(callable $continuation = null)
    {
        $this->continuation = $continuation;
        $this->next();
    }

    /**
     * @param mixed|null $result
     * @param \Throwable|\Exception|null $ex
     * @internal
     */
    public function next($result = null, $ex = null)
    {
        if ($ex instanceof CancelTaskException) {
            if ($continuation = $this->continuation) {
                $continuation(null, $ex);
            }
            return;
        }

        try {
            if ($ex) {
                $value = $this->generator->throw($ex);
            } else {
                if ($this->isfirst) {
                    $this->isfirst = false;
                    $value = $this->generator->current();
                } else {
                    $value = $this->generator->send($result);
                }
            }

            if ($this->generator->valid()) {
                if ($value instanceof Syscall) {
                    $value = $value($this);
                }

                if ($value instanceof \Generator) {
                    $value = new self($value, $this);
                }

                if ($value instanceof Async) {
                    $value->begin([$this, "next"]);
                } else {
                    $this->next($value, null);
                }
            } else {
                if ($continuation = $this->continuation) {
                    $continuation($result, null);
                }
            }
        } catch (\Exception $ex) {
            if ($this->generator->valid()) {
                $this->next(null, $ex);
            } else {
                if ($continuation = $this->continuation) {
                    $continuation(null, $ex);
                }
            }
        }
    }
}