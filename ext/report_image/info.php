<?php declare(strict_types=1);

class ReportImageInfo extends ExtensionInfo
{
    public const KEY = "report_image";

    public $key = self::KEY;
    public $name = "Report Images";
    public $url = "http://atravelinggeek.com/";
    public $authors = ["ATravelingGeek"=>"atg@atravelinggeek.com"];
    public $license = self::LICENSE_GPLV2;
    public $description = "Report images as dupes/illegal/etc";
    public $version = "0.3a";
}
