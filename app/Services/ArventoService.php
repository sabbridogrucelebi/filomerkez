<?php

namespace App\Services;

use App\Models\VehicleTrackingSetting;
use Illuminate\Support\Facades\Http;
use Exception;

class ArventoService
{
    protected $url = "http://ws.arvento.com/v1/report.asmx";
    protected $setting;

    public function __construct(VehicleTrackingSetting $setting)
    {
        $this->setting = $setting;
    }

    /**
     * Arvento Web Servisine manuel SOAP isteği gönderir.
     */
    protected function call($method, $params = [])
    {
        try {
            // Güvenlik: Method adı whitelist dışı olamaz ve XML güvenli olmalı
            $safeMethod = preg_replace('/[^A-Za-z0-9]/', '', (string) $method);

            $xmlParams = "";
            foreach ($params as $key => $value) {
                // XML Injection, CRLF Injection ve özel karakter kaçırma
                $safeKey = preg_replace('/[^A-Za-z0-9_]/', '', (string) $key);
                $safeValue = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xmlParams .= "<{$safeKey}>{$safeValue}</{$safeKey}>";
            }

            $envelope = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <' . $safeMethod . ' xmlns="http://www.arvento.com/">
      ' . $xmlParams . '
    </' . $safeMethod . '>
  </soap:Body>
</soap:Envelope>';

            $response = Http::timeout(15)->withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction'   => 'http://www.arvento.com/' . $safeMethod,
            ])->withBody($envelope, 'text/xml')->post($this->url);

            if ($response->successful()) {
                $body = $response->body();

                // Check if Arvento returned a JSONP callback BEFORE the XML envelope
                // Format: ([{...}]);<?xml ...
                if (preg_match('/^\(\[.*?\]\);/s', $body, $jsonpMatches)) {
                    return $jsonpMatches[0]; // Return the raw JSONP string so getVehicleStatus can parse it
                }

                $resultNode = $safeMethod . "Result";
                if (preg_match('/<' . $resultNode . '[^>]*>(.*?)<\/' . $resultNode . '>/s', $body, $matches)) {
                    return html_entity_decode($matches[1]);
                }
                return null;
            }

            return null;
        } catch (Exception $e) {
            \Log::error("Arvento API Error ({$safeMethod}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Tüm araçların anlık konum bilgilerini (JSON formatında) çeker.
     * Yetki listesindeki: GetVehicleStatusJSON
     */
    public function getVehicleStatus()
    {
        $result = $this->call('GetVehicleStatusJSON', [
            'Username' => $this->setting->username,
            'PIN1'     => $this->setting->app_id ?? '',
            'PIN2'     => $this->setting->app_key ?? '',
            'callback' => '',
        ]);

        if (empty($result)) return [];

        $mappedPlates = $this->getMappedLicensePlates();

        try {
            // Arvento's JSON endpoint might return raw JSON wrapped in callback, before XML.
            // e.g. ([{"Address":"..."}]);<?xml...
            if (preg_match('/^\(\[(.*?)\]\);/s', $result, $matches)) {
                $jsonStr = '[' . $matches[1] . ']';
                $data = json_decode($jsonStr, true);
                if (is_array($data)) {
                    $vehicles = [];
                    foreach ($data as $v) {
                        $node = (string)($v['Node'] ?? '');
                        $vehicles[] = [
                            'Node'         => $node,
                            'LicensePlate' => $mappedPlates[$node] ?? (string)($v['LicensePlate'] ?? $node ?? 'Plakasız'),
                            'Latitude'     => (float)($v['LatitudeY'] ?? $v['Latitude'] ?? 0),
                            'Longitude'    => (float)($v['LongitudeX'] ?? $v['Longitude'] ?? 0),
                            'Speed'        => (int)($v['Speed'] ?? 0),
                            'Address'      => (string)($v['Address'] ?? ''),
                            'Course'       => (int)($v['Course'] ?? 0),
                            'Datetime'     => isset($v['LocalDateTime']) ? date('d.m.Y H:i', strtotime($v['LocalDateTime'])) : '',
                            'ACC'          => (
                                (isset($v['Alarm']) && (stripos($v['Alarm'], 'Kontak A') !== false || stripos($v['Alarm'], 'Motor') !== false)) ||
                                (isset($v['Status']) && (stripos($v['Status'], 'Kontak A') !== false || stripos($v['Status'], 'Motor') !== false)) ||
                                (isset($v['AlarmName']) && (stripos($v['AlarmName'], 'Kontak A') !== false)) ||
                                (isset($v['Icon']) && $v['Icon'] == 2) ||
                                !empty($v['Ignition']) ||
                                !empty($v['Acc']) ||
                                !empty($v['ACC']) ||
                                !empty($v['Engine'])
                            ),
                        ];
                    }
                    return $vehicles;
                }
            }

            // Fallback for standard JSON result if it's properly embedded
            $data = json_decode($result, true);
            if (isset($data['GetVehicleStatusJSON'])) {
                $vehicles = [];
                foreach ($data['GetVehicleStatusJSON'] as $v) {
                    $node = (string)($v['Node'] ?? '');
                        $vehicles[] = [
                            'Node'         => $node,
                            'LicensePlate' => $mappedPlates[$node] ?? (string)($v['LicensePlate'] ?? $node ?? 'Plakasız'),
                            'Latitude'     => (float)($v['LatitudeY'] ?? $v['Latitude'] ?? 0),
                            'Longitude'    => (float)($v['LongitudeX'] ?? $v['Longitude'] ?? 0),
                            'Speed'        => (int)($v['Speed'] ?? 0),
                            'Address'      => (string)($v['Address'] ?? ''),
                            'Course'       => (int)($v['Course'] ?? 0),
                            'Datetime'     => isset($v['LocalDateTime']) ? date('d.m.Y H:i', strtotime($v['LocalDateTime'])) : '',
                            'ACC'          => (
                                (isset($v['Alarm']) && (stripos($v['Alarm'], 'Kontak A') !== false || stripos($v['Alarm'], 'Motor') !== false)) ||
                                (isset($v['Status']) && (stripos($v['Status'], 'Kontak A') !== false || stripos($v['Status'], 'Motor') !== false)) ||
                                (isset($v['AlarmName']) && (stripos($v['AlarmName'], 'Kontak A') !== false)) ||
                                (isset($v['Icon']) && $v['Icon'] == 2) ||
                                !empty($v['Ignition']) ||
                                !empty($v['Acc']) ||
                                !empty($v['ACC']) ||
                                !empty($v['Engine'])
                            ),
                        ];
                }
                return $vehicles;
            }

            return [];
        } catch (Exception $e) {
            \Log::error("Arvento JSON Parse Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Belirli bir aracın bugün yaptığı maksimum hızı bulur.
     * Yetki listesindeki: SpeedReport
     */
    public function getVehicleDailyStats($licensePlate)
    {
        $vehicles = $this->getVehicleStatus();
        $targetVehicle = collect($vehicles)->filter(function($v) use ($licensePlate) {
            return ($v['LicensePlate'] == $licensePlate) || ($v['Node'] == $licensePlate);
        })->first();
        
        if (!$targetVehicle) return null;

        $nodeId = $targetVehicle['Node'];
        $todayStart = now()->startOfDay()->format('d.m.Y H:i:s');
        $todayEnd = now()->format('d.m.Y H:i:s');

        // SpeedReport metodunu kullanıyoruz
        $result = $this->call('SpeedReport', [
            'Username'  => $this->setting->username,
            'PIN1'      => $this->setting->app_id ?? '',
            'PIN2'      => $this->setting->app_key ?? '',
            'StartDate' => $todayStart,
            'EndDate'   => $todayEnd,
            'Node'      => $nodeId,
            'Group'     => '',
            'SpeedLimit' => '0',
            'Compress'  => '0',
            'Language'  => '0',
        ]);

        if (!$result) return null;

        try {
            // SpeedReport sonucunu ayıkla (XML DataSet döner)
            $wrapped = "<root>".$result."</root>";
            $xml = simplexml_load_string($wrapped);
            $rows = $xml->xpath('//Table');
            
            $maxSpeed = 0;
            foreach ($rows as $row) {
                $speed = (int)$row->Hız;
                if ($speed > $maxSpeed) $maxSpeed = $speed;
            }

            return [
                'max_speed' => $maxSpeed,
                'distance' => 0, // SpeedReport mesafe dönmeyebilir
            ];
        } catch (Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Plaka - Cihaz No eşleşmelerini çeker.
     * Yetki listesindeki: GetLicensePlateNodeMappings
     */
    public function getLicensePlateNodeMappings()
    {
        return $this->call('GetLicensePlateNodeMappings', [
            'Username' => $this->setting->username,
            'PIN1'     => $this->setting->app_id ?? '',
            'PIN2'     => $this->setting->app_key ?? '',
        ]);
    }

    public function getMappedLicensePlates()
    {
        return cache()->remember('arvento_plates_v3_' . $this->setting->company_id, 300, function() {
            $xmlString = $this->getLicensePlateNodeMappings();
            if (!$xmlString) return [];
            
            $mapping = [];
            try {
                // Kolay parse için namespace prefixlerini temizle
                $xmlString = preg_replace('/(<\/?)(diffgr:|msdata:|xs:)/i', '$1', $xmlString);
                $xmlString = preg_replace('/\s+(diffgr:|msdata:|xs:)[a-zA-Z0-9]+="[^"]*"/i', '', $xmlString);
                $xmlString = str_replace('xmlns="http://www.arvento.com/"', '', $xmlString);
                
                $xml = simplexml_load_string('<root>' . $xmlString . '</root>');
                if ($xml) {
                    $rows = $xml->xpath('//tblPlaka');
                    foreach ($rows as $row) {
                        $node = (string)$row->Cihaz_x0020_No;
                        $plaka = (string)$row->Plaka;
                        if ($node && $plaka) {
                            $mapping[$node] = trim($plaka);
                        }
                    }
                }
            } catch (Exception $e) {
                \Log::error("Arvento Plate Mapping Error: " . $e->getMessage());
            }
            return $mapping;
        });
    }

    /**
     * Araçların günlük ilk kontak açılma (İlk Çalışma) raporunu çeker.
     * $date formatı: Y-m-d (Örn: 2026-05-21)
     */
    public function getDailyFirstContactReport($date, $nodeList = '')
    {
        // Arvento özel tarih formatı: YmdHis (Örn: 20260521000000)
        $dateObj = \Carbon\Carbon::parse($date);
        $date1 = $dateObj->format('Ymd') . "000000";
        $date2 = $dateObj->format('Ymd') . "235959";

        $result = $this->call('ContactAlarm', [
            'Username'  => $this->setting->username,
            'PIN1'      => $this->setting->app_id ?? '',
            'PIN2'      => $this->setting->app_key ?? '',
            'StartDate' => $date1,
            'EndDate'   => $date2,
            'Node'      => $nodeList,
            'Group'     => '',
            'Compress'  => '0',
            'MinuteDif' => '0',
            'Language'  => '1', // 1=English (PKTTYPE C333/C334 is safer though)
        ]);

        if (!$result) return [];

        try {
            // Kolay parse için namespace prefixlerini temizle
            $xmlString = preg_replace('/(<\/?)(diffgr:|msdata:|xs:)/i', '$1', $result);
            $xmlString = preg_replace('/\s+(diffgr:|msdata:|xs:)[a-zA-Z0-9]+="[^"]*"/i', '', $xmlString);
            $xmlString = str_replace('xmlns="http://www.arvento.com/"', '', $xmlString);

            $xml = simplexml_load_string('<root>' . $xmlString . '</root>');
            if (!$xml) return [];

            $rows = $xml->xpath('//Table1');
            
            $firstContacts = [];
            foreach ($rows as $row) {
                // PKTTYPE C333 = Ignition Switched On
                if ((string)$row->PKTTYPE === 'C333') {
                    $node = (string)$row->NODE;
                    // İlk gördüğümüz "C333" (kronolojik sıralı gelir) o günkü İLK kontak açılışıdır.
                    if (!isset($firstContacts[$node])) {
                        $firstContacts[$node] = [
                            'LicensePlate' => (string)$row->LICENSEPLATE,
                            'Driver' => (string)$row->DRIVER,
                            'DateTime' => date('d.m.Y H:i', strtotime((string)$row->LOCALDATETIME)),
                            'Latitude' => (float)$row->Latitude,
                            'Longitude' => (float)$row->Longitude,
                            'Address' => (string)$row->Address,
                        ];
                    }
                }
            }

            return $firstContacts;
        } catch (Exception $e) {
            \Log::error("Arvento ContactAlarm Parse Error: " . $e->getMessage());
            return [];
        }
    }
}
