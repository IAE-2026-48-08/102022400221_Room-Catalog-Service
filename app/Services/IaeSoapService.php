<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AuditLog;

class IaeSoapService
{
    private string $endpoint;
    private string $teamId;

    public function __construct()
    {
        $this->endpoint = env('SOAP_AUDIT_URL');
        $this->teamId   = env('CENTRAL_TEAM_API_KEY');
    }

    /**
     * Kirim audit log ke SOAP endpoint dosen.
     * Transformasi data PHP/JSON → XML Envelope kaku sesuai skema.
     *
     * @param string $token        Bearer token dari SSO
     * @param string $activityName Nama aktivitas bisnis (e.g. "RoomAssigned")
     * @param array  $logData      Data transaksi yang akan dijadikan LogContent
     * @return string              ReceiptNumber dari response dosen
     */
    public function sendAudit(string $token, string $activityName, array $logData): string
    {
        // Transformasi array PHP ke JSON string untuk CDATA
        $logJson = json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Build SOAP XML Envelope sesuai skema wajib dosen
        $soapXml = $this->buildSoapEnvelope($activityName, $logJson);

        Log::debug('SOAP Request', ['xml' => $soapXml]);

        Log::info('[IAE-SOAP] Mengirim audit', [
            'activity' => $activityName,
            'team_id'  => $this->teamId,
        ]);

        $response = Http::withHeaders([
            'Content-Type'  => 'text/xml; charset=UTF-8',
            'Authorization' => 'Bearer ' . $token,
        ])->send('POST', $this->endpoint, ['body' => $soapXml]);

        Log::debug('SOAP Response', [
            'status'  => $response->status(),
            'headers' => $response->headers(),
            'body'    => $response->body(),
        ]);

        if ($response->failed()) {
            Log::error('[IAE-SOAP] Audit gagal', [
                'status'   => $response->status(),
                'response' => $response->body(),
            ]);
            throw new \RuntimeException('IAE SOAP audit failed: ' . $response->body());
        }

        // Parse ReceiptNumber dari response XML
        $receiptNumber = $this->parseReceiptNumber($response->body());

        Log::info('[IAE-SOAP] Audit berhasil', ['receipt' => $receiptNumber]);

        // Simpan ke tabel audit_logs lokal
        AuditLog::create([
            'team_id'        => $this->teamId,
            'activity_name'  => $activityName,
            'log_content'    => $logJson,
            'receipt_number' => $receiptNumber,
            'status'         => 'SUCCESS',
        ]);

        return $receiptNumber;
    }

    /**
     * Build SOAP Envelope XML sesuai skema yang dosen tentukan.
     */
    private function buildSoapEnvelope(string $activityName, string $logJson): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope
    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:iae="http://iae.central/audit">
  <soap:Body>
    <iae:AuditRequest>
      <iae:TeamID>{$this->teamId}</iae:TeamID>
      <iae:ActivityName>{$activityName}</iae:ActivityName>
      <iae:LogContent><![CDATA[{$logJson}]]></iae:LogContent>
    </iae:AuditRequest>
  </soap:Body>
</soap:Envelope>
XML;
    }

    /**
     * Ekstrak ReceiptNumber dari response XML dosen.
     * Format: <iae:ReceiptNumber>IAE-LOG-2026-XXXXXXXX</iae:ReceiptNumber>
     */
    private function parseReceiptNumber(string $xmlBody): string
    {
        libxml_use_internal_errors(true);

        // Coba parse pakai SimpleXML dengan namespace stripping
        $cleaned = preg_replace('/(<\/?)(\\w+):/', '$1', $xmlBody);
        $xml = simplexml_load_string($cleaned);

        if ($xml !== false) {
            $result = $xml->xpath('//ReceiptNumber');
            if (!empty($result)) {
                return (string) $result[0];
            }
        }

        // Fallback: regex
        preg_match('/<[^:>]*:?ReceiptNumber[^>]*>(IAE-LOG-[^<]+)</', $xmlBody, $matches);

        return $matches[1] ?? 'RECEIPT-UNKNOWN-' . time();
    }
}
