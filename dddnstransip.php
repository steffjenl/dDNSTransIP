<?php
namespace SteffjeNL\dDNSTransIP;

// load autoload.php from composer
require_once(__DIR__ . '/vendor/autoload.php');

use Dotenv;
use Transip\Api\Library\TransipAPI;

class dDNSTransIP
{
    private $transipApi;

    private $domain = '';
    private $subDomain = '';
    private $expire = '';

    private $ipv4Address = '';
    private $ipv6Address = '';

    private $oldIpv4Address = '';

    public function __construct()
    {
        try
        {
            // load .env file in to memory
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
            $dotenv->load();

            $dotenv->required(['TRANSIP_USERNAME', 'TRANSIP_PRIVATEKEY', 'DDNS_DOMAIN', 'DDNS_SUBDOMAIN', 'DDNS_EXPIRE']);
            $dotenv->required(['TRANSIP_GENERATTEWHITELISTONLYTOKENS', 'DDNS_UPDATEAAAA'])->isBoolean();


            // load transip env variables
            $login                          = (string) getenv('TRANSIP_USERNAME');
            $privateKey                     = (string) getenv('TRANSIP_PRIVATEKEY');
            $generateWhitelistOnlyTokens    = (bool) (getenv('TRANSIP_GENERATTEWHITELISTONLYTOKENS') == 'true' ? true : false);

            $this->domain                   = (string) getenv('DDNS_DOMAIN');
            $this->subDomain                = (string) getenv('DDNS_SUBDOMAIN');
            $updateAAARecord                = (bool) (getenv('DDNS_UPDATEAAAA') == 'true' ? true : false);
            $this->expire                   = (string) (empty(getenv('DDNS_EXPIRE')) ? '60' : getenv('DDNS_EXPIRE'));


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
        catch (\Exception $exception)
        {
            $this->log($exception->getMessage());
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
            $dnsEntry->setExpire($this->expire);
            $dnsEntry->setType($type);
            $dnsEntry->setContent($ipAddress);

            $this->transipApi->domainDns()->updateEntry($this->domain, $dnsEntry);
            return;
        }

        $dnsEntry = new \Transip\Api\Library\Entity\Domain\DnsEntry();
        $dnsEntry->setName($this->subDomain);
        $dnsEntry->setExpire($this->expire);
        $dnsEntry->setType($type);
        $dnsEntry->setContent($ipAddress);

        $this->transipApi->domainDns()->addDnsEntryToDomain($this->domain, $dnsEntry);

    }

    private function isIpAddressChanged()
    {
        // when no ip address is empty or not valid we don't update the ip address.
        if (empty($this->ipv4Address) || !filter_var($this->ipv4Address, FILTER_VALIDATE_IP))
        {
            $this->log("there is no valid wan ip address found!");
            return false;
        }
        // when no file ip address exists we must change the ip address for the first-time!
        if (!file_exists(__DIR__ . '/ipaddress'))
        {
            return true;
        }
        // check if ipaddress is changed since last time.
        $this->oldIpv4Address = file_get_contents(__DIR__ . '/ipaddress');

        if ($this->oldIpv4Address != $this->ipv4Address)
        {
            return true;
        }
        // don't change ip address
        return false;
    }

    private function logUpdatedIpAddress()
    {
        file_put_contents(__DIR__ . '/ipaddress', $this->ipv4Address);
        $message = sprintf("Updated record %s from domain %s to ipaddress %s\n", $this->subDomain, $this->domain, $this->ipv4Address);
        $logFile = file_get_contents(__DIR__ . '/ipaddress.log');
        $logFile .= $message;
        file_put_contents(__DIR__ . '/ipaddress.log', $logFile);
        $this->log($message);
    }

    private function log($message)
    {
        $currentTime = date("h:i:s", time());
        echo "[dDNSTransIP][$currentTime] $message \n";
    }
}

$dDNSTransIP = new dDNSTransIP();
