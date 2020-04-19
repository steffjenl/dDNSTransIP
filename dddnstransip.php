<?php
namespace SteffjeNL\dDNSTransIP;

// load autoload.php from composer
require_once(__DIR__ . '/vendor/autoload.php');

use Dotenv;
use Transip\Api\Library\TransipAPI;

class dDNSTransIP
{
    private $transipApi;

    private $domain;
    private $subDomain;

    private $ipv4Address;
    private $ipv6Address;

    private $oldIpv4Address;

    public function __construct()
    {
        // load .env file in to memory
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        $dotenv->required(['TRANSIP_USERNAME', 'TRANSIP_PRIVATEKEY', 'DDNS_DOMAIN', 'DDNS_SUBDOMAIN']);
        $dotenv->required(['TRANSIP_GENERATTEWHITELISTONLYTOKENS', 'DDNS_UPDATEAAAA'])->isBoolean();

        // load transip env variables
        $login                          = getenv('TRANSIP_USERNAME');
        $privateKey                     = getenv('TRANSIP_PRIVATEKEY');
        $generateWhitelistOnlyTokens    = (getenv('TRANSIP_GENERATTEWHITELISTONLYTOKENS') == 'true' ? true : false);

        $this->domain                   = getenv('DDNS_DOMAIN');
        $this->subDomain                = getenv('DDNS_SUBDOMAIN');
        $updateAAARecord                = (getenv('DDNS_UPDATEAAAA') == 'true' ? true : false);

        // load transip api class in to memory
        $this->transipApi = new TransipAPI(
            $login,
            $privateKey,
            $generateWhitelistOnlyTokens
        );

        $this->ipv4Address    = $this->getWanAddress();
        $this->ipv6Address    = $this->getWanAddress(true);

        if ($this->isIpAddressChanged())
        {
            $this->updateDNSEntry($this->ipv4Address, 'A');
            if ($updateAAARecord) $this->updateDNSEntry($this->ipv6Address, 'AAA');
            $this->logUpdatedIpAddress();
        }
    }

    private function getWanAddress($ipv6 = false)
    {
        $address = 'ifconfig.me';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $address);
        curl_setopt($ch, CURLOPT_IPRESOLVE, ($ipv6 ? CURL_IPRESOLVE_V6 : CURL_IPRESOLVE_V4));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $ipAddress = curl_exec($ch);
        curl_close($ch);
        return $ipAddress;
    }

    private function checkIfDNSEntryExists($type)
    {
        $entries = $this->transipApi->domainDns()->getByDomainName($this->domain);
        foreach($entries as $entry)
        {
            if ($entry->getName() == $this->subDomain && $entry->getType() == $type)
            {
                return true;
            }
        }
        return false;
    }

    private function updateDNSEntry($ipAddress, $type)
    {

        if ($this->checkIfDNSEntryExists($type))
        {
            return;
            $dnsEntry = new \Transip\Api\Library\Entity\Domain\DnsEntry();
            $dnsEntry->setName($this->subDomain);
            $dnsEntry->setExpire(60);
            $dnsEntry->setType($type);
            $dnsEntry->setContent($ipAddress);

            $this->transipApi->domainDns()->updateEntry($this->domain, $dnsEntry);
            return;
        }

        $dnsEntry = new \Transip\Api\Library\Entity\Domain\DnsEntry();
        $dnsEntry->setName($this->subDomain);
        $dnsEntry->setExpire(60);
        $dnsEntry->setType($type);
        $dnsEntry->setContent($ipAddress);

        $this->transipApi->domainDns()->addDnsEntryToDomain($this->domain, $dnsEntry);

    }

    private function isIpAddressChanged()
    {
        if (!file_exists(__DIR__ . '/ipaddress'))
        {
            return true;
        }
        $this->oldIpv4Address = file_get_contents(__DIR__ . '/ipaddress');

        if ($this->oldIpv4Address != $this->ipv4Address)
        {
            return true;
        }
        return false;
    }

    private function logUpdatedIpAddress()
    {
        file_put_contents(__DIR__ . '/ipaddress', $this->ipv4Address);

        $logFile = file_get_contents(__DIR__ . '/ipaddress.log');
        $logFile .= sprintf("Updated record %s from domain %s to ipaddress %s\n", $this->subDomain, $this->domain, $this->ipv4Address);
        file_put_contents(__DIR__ . '/ipaddress.log', $logFile);
    }
}

$dDNSTransIP = new dDNSTransIP();
