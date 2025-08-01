<?php
namespace Think\Driver\IpLocation;

use GeoIp2\Database\Reader;

class Geoip
{
    /**
     * @var Reader
     */
    private $handle;
    /**
     * Reader constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        $dbFile = CORE_PATH . 'Driver/IpLocation/data/GeoLite2-City.mmdb';
        if(!file_exists($dbFile)) {
            throw new \RuntimeException('GeoIp 数据库文件不存在，请下载它并将其存放于' . $dbFile . '。');
        }
        $this->handle = new Reader($dbFile, ['zh-CN']);
    }

    public function find($ip)
    {
        $ips = explode('.', $ip);
        $result = [
            'ip' => $ip,
            'country_id' => $ips[0] . '.' . $ips[1] . '--',
            'country' => $ips[0] . '.' . $ips[1] . '--',
            'region' => $ips[0] . '.' . $ips[1] . '--',
            'city' => $ips[0] . '.' . $ips[1] . '--'
        ];
        $record = $this->handle->city($ip);
        if($record->country->name) {
            $result['country_id'] = $record->country->isoCode;
            $result['region'] = $result['city'] = $result['country'] = $record->country->name;
        }
        if($record->mostSpecificSubdivision->name) {
            $result['city'] = $result['region'] = $record->mostSpecificSubdivision->name;
        }
        if($record->city->name) {
            $result['city'] = $record->city->name;
        }
        return $result;
    }
}
