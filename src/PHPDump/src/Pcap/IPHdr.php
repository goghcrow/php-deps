<?php

namespace Minimalism\PHPDump\Pcap;


use Minimalism\PHPDump\Buffer\Buffer;


/**
 * Class IpHdr
 * @package Minimalism\PHPDump\Pcap
 *
 * [
 * version + ihl
 * $pcap->uC . "version_ihl/",  // 4bit版本 === 4(IPV4) + 4bit首部长度（IHL）
 * 版本
 *
 * IP报文首部的第一个字段是4位版本字段。对IPv4来说，这个字段的值是4。
 * 第二个字段是4位首部长度，说明首部有多少32位字长。
 * 首部长度（IHL）
 * 由于IPv4首部可能包含数目不定的选项，这个字段也用来确定数据的偏移量。
 * 这个字段的最小值是5（RFC 791），最大值是15。
 * dscp + ecn
 *
 * $pcap->uC . "services/", 8bit
 * DiffServ（DSCP）
 *
 * 最初被定义为服务类型字段，但被RFC 2474重定义为DiffServ。
 * 新的需要实时数据流的技术会应用这个字段，一个例子是VoIP。
 * 显式拥塞通告（ECN）
 * 在RFC 3168中定义，允许在不丢弃报文的同时通知对方网络拥塞的发生。
 * ECN是一种可选的功能，仅当两端都支持并希望使用，且底层网络支持时才被使用。
 *
 * $pcap->u16 . "length/",  16bit 全长 min20 ~ max65535
 * 全长
 * 这个16位字段定义了报文总长，包含首部和数据，单位为字节。
 * 这个字段的最小值是20（20字节首部+0字节数据），最大值是65,535。
 * 所有主机都必须支持最小576字节的报文，但大多数现代主机支持更大的报文。
 * 有时候子网会限制报文的大小，这时报文就必须被分片。
 *
 * $pcap->u16 . "identification/",  标识符 16bit
 * 标识符
 *
 * 这个字段主要被用来唯一地标识一个报文的所有分片。
 * 一些实验性的工作建议将此字段用于其它目的，例如增加报文跟踪信息以协助探测伪造的源地址。
 *
 * flags + offset
 * $pcap->u16 . "flags_offset/",  16bit = 3bit标志 + 13bit分片偏移
 * 标志
 *
 * 这个3位字段用于控制和识别分片，它们是：
 * 位0：保留，必须为0；
 * 位1：禁止分片（DF）；
 * 位2：更多分片（MF）。
 * 如果DF标志被设置但路由要求必须分片报文，此报文会被丢弃。这个标志可被用于发往没有能力组装分片的主机。
 * 当一个报文被分片，除了最后一片外的所有分片都设置MF标志。不被分片的报文不设置MF标志：它是它自己的最后一片。
 * 分片偏移这个13位字段指明了每个分片相对于原始报文开头的偏移量，以8字节作单位。
 *
 * $pcap->uC . "ttl/",  8bit
 * 存活时间（TTL）
 *
 * 这个8位字段避免报文在互联网中永远存在（例如陷入路由环路）。
 * 存活时间以秒为单位，但小于一秒的时间均向上取整到一秒。
 * 在现实中，这实际上成了一个跳数计数器：报文经过的每个路由器都将此字段减一，当此字段等于0时，报文不再向下一跳传送并被丢弃。
 * 常规地，一份ICMP报文被发回报文发送端说明其发送的报文已被丢弃。这也是traceroute的核心原理。
 *
 * $pcap->uC . "protocol/",
 * 协议
 * 这个字段定义了该报文数据区使用的协议。IANA维护着一份协议列表（最初由RFC 790定义）。
 *
 * 协议字段值 协议名 缩写
 * 1 互联网控制消息协议 ICMP 0x01
 * 2 互联网组管理协议  IGMP 0x02
 * 6 传输控制协议  TCP 0x06
 * 17  用户数据报协议 UDP 0x11
 * 41  IPv6封装  - 0x29
 * 89  开放式最短路径优先 OSPF
 * 132 流控制传输协议 SCTP
 *
 * $pcap->u16 . "checksum/",
 * 首部检验和
 *
 * 这个16位检验和字段用于对首部查错。
 * 在每一跳，计算出的首部检验和必须与此字段进行比对，如果不一致，此报文被丢弃。
 * 值得注意的是，数据区的错误留待上层协议处理——用户数据报协议和传输控制协议都有检验和字段。
 * 因为生存时间字段在每一跳都会发生变化，意味着检验和必须被重新计算，RFC 1071这样定义计算检验和的方法：
 * The checksum field is the 16-bit one's complement of the one's complement sum of all 16-bit words in the header.
 * For purposes of computing the checksum, the value of the checksum field is zero.
 *
 * $pcap->u32 . "source/",
 * 源地址
 *
 * 一个IPv4地址由四个字节共32位构成，此字段的值是将每个字节转为二进制并拼在一起所得到的32位值。
 * 例如，10.9.8.7是00001010000010010000100000000111。
 * 这个地址是报文的发送端。但请注意，因为NAT的存在，这个地址并不总是报文的真实发送端，因此发往此地址的报文会被送往NAT设备，并由它被翻译为真实的地址。
 *
 * $pcap->u32 . "destination",
 * 目的地址
 *
 * 与源地址格式相同，但指出报文的接收端。
 * ]
 *
 * ignoring options
 * 选项
 * 附加的首部字段可能跟在目的地址之后，但这并不被经常使用。
 * 请注意首部长度字段必须包括足够的32位字来放下所有的选项（包括任何必须的填充以使首部长度能够被32位整除）。
 * 当选项列表的结尾不是首部的结尾时，EOL（选项列表结束，0x00）选项被插入列表末尾。下表列出了可能的选项：
 *
 * 字段  长度（位） 描述
 * 备份  1     当此选项需要被备份到所有分片中时，设为1。
 * 类   2     常规的选项类别，0为“控制”，2为“查错和措施”，1和3保留。
 * 数字  5     指明一个选项。
 * 长度  8     指明整个选项的长度，对于简单的选项此字段可能不存在。
 * 数据  可变    选项相关数据，对于简单的选项此字段可能不存在。
 *
 * 注：如果首部长度大于5，那么选项字段必然存在并必须被考虑。
 * 注：备份、类和数字经常被一并称呼为“类型”。
 * 宽松的源站选路（LSRR）和严格的源站选路（SSRR）选项不被推荐使用，因其可能带来安全问题。许多路由器会拒绝带有这些选项的报文。
 */
class IPHdr
{
    const SIZE = 20;

    const VER4 = 4;

    const PROTO_ICMP = 0x01;
    const PROTO_IGMP = 0x02;
    const PROTO_TCP = 0x06;
    const PROTO_UDP = 0x11;
    const PROTO_IPV6 = 0x29;

    public $version;
    public $ihl;
    public $services;
    public $length;
    public $identification;
    public $flags;
    public $offset;
    public $ttl;
    public $protocol;
    public $checksum;
    public $source_ip;
    public $destination_ip;

    public static function unpack(Buffer $recordBuffer, Pcap $pcap)
    {
        if ($recordBuffer->readableBytes() < self::SIZE) {
            sys_abort("buffer is too small to read ip header");
        }

        $ip_hdr = [
            $pcap->uC . "version_ihl/", // version + ihl 4bit版本 === 4(IPV4) + 4bit首部长度（IHL）
            $pcap->uC . "services/",// 8bit
            $pcap->u16 . "length/", // 16bit 全长 min20 ~ max65535
            $pcap->u16 . "identification/", // 标识符 16bit
            $pcap->u16 . "flags_offset/", // 16bit = 3bit标志 + 13bit分片偏移
            $pcap->uC . "ttl/", // 8bit
            $pcap->uC . "protocol/",
            $pcap->u16 . "checksum/",
            $pcap->u32 . "source/",
            $pcap->u32 . "destination",
        ];

        $ip = unpack(implode($ip_hdr), $recordBuffer->read(20));
        if(!isset($ip["version_ihl"])) {
            sys_abort("malformed ip header");
        }

        $ip['version'] = $ip['version_ihl'] >> 4;
        $ip['ihl'] = $ip['version_ihl'] & 0xf;
        unset($ip['version_ihl']);
        $ip['flags'] = $ip['flags_offset'] >> 13;
        $ip['offset'] = $ip['flags_offset'] & 0x1fff;
        $ip['source_ip'] = long2ip($ip['source']);
        $ip['destination_ip'] = long2ip($ip['destination']);


        $self = new static;

        $self->version = $ip["version"];
        $self->ihl = $ip["ihl"];
        $self->services = $ip["services"];
        $self->length = $ip["length"];
        $self->identification = $ip["identification"];
        $self->flags = $ip["flags"];
        $self->offset = $ip["offset"];

        $self->ttl = $ip["ttl"];
        $self->protocol = $ip["protocol"];
        $self->checksum = $ip["checksum"];
        $self->source_ip = $ip["source_ip"];
        $self->destination_ip = $ip["destination_ip"];

        return $self;
    }
}