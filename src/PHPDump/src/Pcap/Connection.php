<?php

namespace Minimalism\PHPDump\Pcap;


use Minimalism\PHPDump\Buffer\Buffer;
use Minimalism\PHPDump\Buffer\BufferFactory;

class Connection
{
    /**
     * @var RecordHdr
     */
    public $recordHdr;

    /**
     * @var LinuxSLLHdr
     */
    public $linuxSLLHdr;

    /**
     * @var IPHdr
     */
    public $IPHdr;

    /**
     * @var TCPHdr
     */
    public $TCPHdr;

    /**
     * @var Buffer
     */
    public $buffer;

    /**
     * @var Protocol
     */
    public $protocol;

    /**
     * 当前连接未解析完成的包
     *
     * @var Packet $currentPacket
     *
     * 有的协议 在isReceiveCompleted已经尝试parser一次
     * 可以暂时保存起来
     * 然后unpack中可以直接获取使用
     */
    public $currentPacket;

    public function __construct(RecordHdr $recordHdr, LinuxSLLHdr $linuxSLLHdr, IPHdr $IPHdr, TCPHdr $TCPHdr)
    {
        $this->recordHdr = $recordHdr;
        $this->linuxSLLHdr = $linuxSLLHdr;
        $this->IPHdr = $IPHdr;
        $this->TCPHdr = $TCPHdr;

        $this->buffer = BufferFactory::make();
    }

    public function setProtocol(Protocol $protocol)
    {
        $this->protocol = $protocol;
    }

    public function isDetected()
    {
        return $this->protocol !== null;
    }

    public function loopAnalyze()
    {
        // 这里有个问题: 如果tcpdump 捕获的数据不全
        // 需要使用对端回复的ip分节的 ack-1 来确认此条ip分节的长度
        // 从而检查到接受数据是否有问题, 这里简化处理, 没有检测

        while (true) {
            if ($this->protocol->isReceiveCompleted($this)) {

                $packet = $this->protocol->unpack($this);

                if ($packet->beforeAnalyze()) {
                    try {
                        $packet->analyze($this);
                        $packet->afterAnalyze();
                    } catch (\Exception $ex) {
                        echo $ex, "\n";
                        $protocolName = $this->protocol->getName();
                        sys_abort("protocol $protocolName pack analyze fail");
                    }
                }

            } else {
                break;
            }
        }
    }
}