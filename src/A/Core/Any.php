<?php
/**
 * Created by IntelliJ IDEA.
 * User: chuxiaofeng
 * Date: 17/3/5
 * Time: 下午4:04
 */

namespace Minimalism\A\Core;


/**
 * Class Any
 * @package Minimalism\A\Core
 *
 * 任何一个完成或失败
 */
class Any implements Async
{
    public $parent;
    public $tasks;
    public $continuation;
    public $done;

    /**
     * AllTasks constructor.
     * @param \Generator[] $tasks
     * @param AsyncTask $parent
     */
    public function __construct(array $tasks, AsyncTask $parent = null)
    {
        $this->tasks = $tasks;
        $this->parent = $parent;
        assert(!empty($tasks));
        $this->done = false;
    }

    /**
     * 开启异步任务，立即返回，任务完成回调$continuation
     * @param callable $continuation
     *      void(mixed $result = null, \Throwable|\Exception $ex = null)
     * @return void
     */
    public function begin(callable $continuation)
    {
        $this->continuation = $continuation;
        foreach ($this->tasks as $id => $task) {
            (new AsyncTask($task, $this->parent))->begin($this->continuation($id));
        };
    }

    private function continuation($id)
    {
        return function($r, $ex = null) use($id) {
            if ($this->done) {
                return;
            }
            $this->done = true;

            if ($this->continuation) {
                $k = $this->continuation;
                $k($r, $ex);
            }
        };
    }
}