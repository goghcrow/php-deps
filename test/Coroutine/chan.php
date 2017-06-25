<?php

namespace Minimalism\Test\Coroutine;

use function Minimalism\Coroutine\callcc;
use function Minimalism\Coroutine\chan;
use Minimalism\Coroutine\Channel;
use function Minimalism\Coroutine\go;
use function Minimalism\Coroutine\self;
use Minimalism\Coroutine\Time;

require __DIR__ . "/../../vendor/autoload.php";



swoole_async_set([
    "disable_dns_cache" => true,
    "dns_lookup_random" => true,
]);


function gethostbyname($host)
{
    return callcc(function($k) use($host) {
        swoole_async_dns_lookup($host, function($_, $ip) use($k) {
            $k($ip);
        });
    });
}

// 人工执行 Channel
call_user_func(function() {
    $ch = chan();

    ($ch->recv())->start(function($r, $ex) {
        assert($r === 42);
    });

    ($ch->send(42))->start(function($r, $ex) {
        assert($r === null);
    });
});



// 通过channel传递channel
call_user_func(function() {
    $ch = chan();
    $buf = "";

    go(function() use ($ch, &$buf) {
        $anotherCh = chan();
        yield $ch->send($anotherCh);
        $buf .=  "send another channel\n";
        yield $anotherCh->send("HELLO");
        $buf .=  "send hello through another channel\n";
    });

    go(function() use($ch, &$buf) {
        /** @var Channel $anotherCh */
        $anotherCh = (yield $ch->recv());
        $buf .=  "recv another channel\n";
        $val = (yield $anotherCh->recv());
        $buf .=  $val . "\n";
        assert($buf === <<<RAW
send another channel
recv another channel
send hello through another channel
HELLO

RAW
);
    });
});


// 通过channel传递异步数据
call_user_func(function() {
    $ch = chan();

    go(function() use($ch) {
        // $buf .=  "before recv\n";
        $ip = (yield $ch->recv());
        // var_dump($ip);
        assert(ip2long($ip));
    });

    go(function() use($ch) {
        // $buf .=  "before send\n";
        $ip = (yield gethostbyname("www.google.com"));
        yield $ch->send($ip);
        // $buf .=  "after send\n";
    });
});



// timeout
call_user_func(function() {
    /*
    $ch = chan();

    go(function() use($ch) {
        $ex = null;
        try {
            recv:
            yield $ch->recv(1000);
        } catch (\Exception $ex) {
            $buf .=  "ex\n";
            goto recv;
        }
    });


    go(function() use($ch) {
        yield Time::sleep(2000);
        $buf .=  "send\n";
        yield $ch->send(42);
    });
    */
});


// buffered channel
call_user_func(function() {
    $ch = chan(3);
    
    $buf = "";

    go(function() use($ch, &$buf) {
        $recv = (yield $ch->recv());
        $buf .= "recv $recv\n";
        $recv = (yield $ch->recv());
        $buf .= "recv $recv\n";
        $recv = (yield $ch->recv());
        $buf .=  "recv $recv\n";
        $recv = (yield $ch->recv());
        $buf .=  "recv $recv\n";
        assert($buf === <<<RAW
send 1
send 2
send 3
recv 1
recv 2
recv 3
send 4
recv 4

RAW
    );
    });

    go(function() use($ch, &$buf) {
        yield $ch->send(1);
        $buf .=  "send 1\n";
        yield $ch->send(2);
        $buf .=  "send 2\n";
        yield $ch->send(3);
        $buf .=  "send 3\n";
        yield $ch->send(4);
        $buf .=  "send 4\n";
    });
});


// 测试 buffer 行为
call_user_func(function() {
    $ch = chan(rand(1, 4));

    go(function() use($ch, &$buf) {
        $recv = (yield $ch->recv());
        $buf .=  "recv $recv\n";
        $recv = (yield $ch->recv());
        $buf .=  "recv $recv\n";
        $recv = (yield $ch->recv());
        $buf .=  "recv $recv\n";
        $recv = (yield $ch->recv());
        $buf .=  "recv $recv\n";
        // echo $buf;
    });

    go(function() use($ch, &$buf) {
        yield $ch->send(1);
        $buf .=  "send 1\n";
        yield $ch->send(2);
        $buf .=  "send 2\n";
        yield $ch->send(3);
        $buf .=  "send 3\n";
        yield $ch->send(4);
        $buf .=  "send 4\n";
    });
});



call_user_func(function() {
    $ch = chan(1);

    $buf = "";

    go(function() use($ch, &$buf) {
        yield $ch->send(1);
        $buf .= "send 1\n";
        yield $ch->send(2);
        $buf .= "send 2\n";

        yield Time::sleep(200);
        $buf .= "send: after 1s\n";
    });


    go(function() use($ch, &$buf) {
        $recv = (yield $ch->recv());
        $buf .= "recv $recv\n";

        // sleep 不会阻塞第二次send
        yield Time::sleep(100);
        $buf .= "recv: after 1s\n";

        $recv = (yield $ch->recv());
        $buf .= "recv $recv\n";

        assert($buf === <<<RAW
send 1
recv 1
send 2
recv: after 1s
recv 2

RAW
);
    });
});





// 生产者消费者
$producerConsumer = function() {
    $ch = chan(2);

    go(function() use($ch) {
        while (true) {
            list($host, $ip) = (yield $ch->recv());
            echo "$host: $ip\n";
        }
    });

    go(function() use($ch) {
        while (true) {
            $host = "www.google.com";
            $ip = (yield gethostbyname($host));
            yield $ch->send([$host, $ip]);
        }
    });

    go(function() use($ch) {
        while (true) {
            $host = "www.bing.com";
            $ip = (yield gethostbyname($host));
            yield $ch->send([$host, $ip]);
        }
    });
};


// ping pong
$pingPong = function() {
    $pingCh = chan();
    $pongCh = chan();


    go(function() use($pingCh, $pongCh) {
        while (true) {
            echo (yield $pingCh->recv());
            yield $pongCh->send("PONG\n");

            yield Time::sleep(100);
        }
    });

    go(function() use($pingCh, $pongCh) {
        while (true) {
            echo (yield $pongCh->recv());
            yield $pingCh->send("PING\n");

            yield Time::sleep(100);
        }
    });

    go(function() use($pingCh) {
        echo "start up\n";
        yield $pingCh->send("PING\n");
    });
};



// 测试多生产者与多消费者
$_ = function() {
    $ch = chan(2);

    go(function() use($ch, &$buf) {
        while (true) {
            $recv = (yield $ch->recv());
            echo  "a recv\n";
            yield Time::sleep(1);
        }
    });

    go(function() use($ch, &$buf) {
        while (true) {
            $recv = (yield $ch->recv());
            echo  "b recv\n";
            yield Time::sleep(1);
        }
    });

    go(function() use($ch, &$buf) {
        while (true) {
            yield $ch->send(null);
            echo  "c send\n";
            yield Time::sleep(1);
        }
    });

    go(function() use($ch, &$buf) {
        while (true) {
            yield $ch->send(null);
            echo  "d send\n";
            yield Time::sleep(1);
        }
    });
};



$_ = function() {
    $ch = chan();

    go(function() use($ch) {
        while (true) {
            yield $ch->send("producer 1");
            yield Time::sleep(1000);
        }
    });

    go(function() use($ch) {
        while (true) {
            yield $ch->send("producer 2");
            yield Time::sleep(1000);
        }
    });

    go(function() use($ch) {
        while (true) {
            $recv = (yield $ch->recv());
            echo "consumer1 recv from $recv\n";
        }
    });

    go(function() use($ch) {
        while (true) {
            $recv = (yield $ch->recv());
            echo "consumer2 recv from $recv\n";
        }
    });
};


$_ = function() {

    function ping($val)
    {
        echo yield self(); // 查看 task tree

        echo "ping: $val\n";
        yield Time::sleep(500);
        yield pong($val + 1);
    }

    function pong($val)
    {
        echo "pong: $val\n";
        yield Time::sleep(500);
        yield ping($val + 1);
    }

    go(function() {
        yield ping(0);
    });

    go(function() {
        yield ping(0);
    });
};