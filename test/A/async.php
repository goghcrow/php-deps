<?php
/**
 * Created by IntelliJ IDEA.
 * User: chuxiaofeng
 * Date: 17/1/31
 * Time: 下午4:34
 */

namespace Minimalism\Test\A;


use function Minimalism\A\Client\async_sleep;
use function Minimalism\A\Client\async_dns_loohup;
use function Minimalism\A\Client\async_get;
use function Minimalism\A\Client\async_post;
use function Minimalism\A\Client\async_request;
use function Minimalism\A\Core\async;
use function Minimalism\A\Core\await;
use function Minimalism\A\Core\cancelTask;
use function Minimalism\A\Core\getCtx;
use function Minimalism\A\Core\setCtx;
use Minimalism\A\Core\Exception\CancelTaskException;

require __DIR__ . "/../../vendor/autoload.php";


// 子任务
async(function() {
    $r = (yield await(function() {
        $r1 = (yield async_dns_loohup("www.baidu.com"));
        $r2 = (yield async_dns_loohup("www.baidu.com"));
        yield [$r1, $r2];
    }));

    $r3 = (yield async_dns_loohup("www.baidu.com"));

    assert($r[0] === $r3);
    assert($r[1] === $r3);
});


// 取消任务 1. yield cancelTask()
async(function() {
    yield cancelTask();
    echo "unreached\n";
    assert(false);
});

// 取消任务 2. 抛出CancelTaskException
async(function() {
    yield;
    throw new CancelTaskException();
    echo "unreached\n";
    assert(false);
});


// 上下文1
async(function() {
    yield setCtx("foo", "bar");
    yield await(function() {
        assert((yield getCtx("foo")) === "bar");
        yield setCtx("hello", "world");
    });
    assert((yield getCtx("hello")) === "world");
});

// 上下文2
async(function() {
    yield await(function() {
        $v = (yield getCtx("hello"));
        assert($v === "world");
    });
}, null, ["hello" => "world"]);



// example
async(function() {
    yield await(function() {
        $ip = (yield async_dns_loohup("www.baidu.com"));

        $r = (yield async_get($ip, 80));
        assert($r->statusCode === 200);
        $r->close();

        $r = (yield async_post($ip, 80, "/",
            ["Connection" => "close"],
            ["cookieK" => "cookieV"],
            "body", 2000));
        assert($r->statusCode === 302);
    });

    yield await(function() {
        $ip = (yield async_dns_loohup("www.baidu.com"));

        $r = (yield async_request($ip, 80, "PUT", "/",
            ["Connection" => "close"]));
        assert($r->statusCode === 200);
    });

    yield async_sleep(1000);

}, function($r, $e) {
    if ($e) {
        assert(false);
    }
});


swoole_timer_after(2000, function() { swoole_event_exit(); });