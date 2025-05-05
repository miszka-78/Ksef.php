<?php
/**
 * KSeF API client for interacting with the Polish e-Invoicing system
 */
class KsefApi {
    private $entity;
    private $apiUrl;
    private $token;
    
    /**
     * Constructor
     * 
     * @param Entity $entity Entity object with KSeF credentials
     */
    public function __construct(Entity $entity) {
        $this->entity = $entity;
        $this->apiUrl = $entity->getKsefApiUrl();
        $this->token = $entity->getKsefToken();
    }
    
    /**
     * Authenticate with KSeF API
     * 
     * @param string $authType Auth type ('token', 'password', etc.)
     * @param array $credentials Credentials for authentication
     * @return array Authentication result with token and expiry
     */
    public function authenticate($authType, $credentials) {
        switch ($authType) {
            case 'token':
                return $this->authenticateWithToken($credentials);
            case 'password':
                return $this->authenticateWithPassword($credentials);
            default:
                throw new Exception("Unsupported authentication type: $authType");
        }
    }
    
    /**
     * Authenticate with token
     * 
     * @param array $credentials Credentials for token auth
     * @return array Authentication result with token and expiry
     */
    private function authenticateWithToken($credentials) {
        $endpoint = '/auth/token';
        $payload = [
            'identifier' => $credentials['identifier'] ?? $this->entity->getKsefIdentifier(),
            'token' => $credentials['token'] ?? ''
        ];
        
        $response = $this->sendRequest('POST', $endpoint, $payload);
        
        if (isset($response['token'])) {
            // Update token in entity
            $token = $response['token'];
            $expiry = isset($response['expiry']) ? $response['expiry'] : date('Y-m-d H:i:s', strtotime('+4 hours'));
            
            $this->token = $token;
            $this->entity->updateKsefToken($token, $expiry);
            
            return [
                'success' => true,
                'token' => $token,
                'expiry' => $expiry
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['error'] ?? 'Authentication failed'
        ];
    }
    
    /**
     * Authenticate with password
     * 
     * @param array $credentials Credentials for password auth
     * @return array Authentication result with token and expiry
     */
    private function authenticateWithPassword($credentials) {
        $endpoint = '/auth/login';
        $payload = [
            'nip' => $credentials['nip'] ?? $this->entity->getTaxId(),
            'password' => $credentials['password'] ?? ''
        ];
        
        $response = $this->sendRequest('POST', $endpoint, $payload);
        
        if (isset($response['token'])) {
            // Update token in entity
            $token = $response['token'];
            $expiry = isset($response['expiry']) ? $response['expiry'] : date('Y-m-d H:i:s', strtotime('+4 hours'));
            
            $this->token = $token;
            $this->entity->updateKsefToken($token, $expiry);
            
            return [
                'success' => true,
                'token' => $token,
                'expiry' => $expiry
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['error'] ?? 'Authentication failed'
        ];
    }
    
    /**
     * Check if token is valid
     * 
     * @return bool Token is valid or not
     */
    public function checkToken() {
        if (!$this->token) {
            return false;
        }
        
        $endpoint = '/auth/status';
        $response = $this->sendRequest('GET', $endpoint);
        
        return isset($response['active']) && $response['active'] === true;
    }
    
    /**
     * Get invoices from KSeF
     * 
     * @param array $params Query parameters
     * @return array List of invoices
     */
    public function getInvoices($params = []) {
        $endpoint = '/invoices/list';
        
        // Default parameters
        $defaultParams = [
            'dateFrom' => date('Y-m-d', strtotime('-30 days')),
            'dateTo' => date('Y-m-d'),
            'pageSize' => 100,
            'pageNumber' => 1
        ];
        
        $queryParams = array_merge($defaultParams, $params);
        
        $response = $this->sendRequest('GET', $endpoint, null, $queryParams);
        
        if (isset($response['invoices'])) {
            return [
                'success' => true,
                'invoices' => $response['invoices'],
                'totalCount' => $response['totalCount'] ?? count($response['invoices']),
                'hasMorePages' => $response['hasMorePages'] ?? false
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['error'] ?? 'Failed to get invoices'
        ];
    }
    
    /**
     * Get invoice details by KSeF reference number
     * 
     * @param string $ksefReferenceNumber KSeF reference number
     * @return array Invoice details
     */
    public function getInvoiceDetails($ksefReferenceNumber) {
        $endpoint = '/invoices/' . urlencode($ksefReferenceNumber);
        
        $response = $this->sendRequest('GET', $endpoint);
        
        if (isset($response['invoice'])) {
            return [
                'success' => true,
                'invoice' => $response['invoice']
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['error'] ?? 'Failed to get invoice details'
        ];
    }
    
    /**
     * Get invoice XML by KSeF reference number
     * 
     * @param string $ksefReferenceNumber KSeF reference number
     * @return array Invoice XML content
     */
    public function getInvoiceXml($ksefReferenceNumber) {
        $endpoint = '/invoices/' . urlencode($ksefReferenceNumber) . '/xml';
        
        $response = $this->sendRequest('GET', $endpoint, null, [], true);
        
        if ($response) {
            return [
                'success' => true,
                'xml' => $response
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Failed to get invoice XML'
        ];
    }
    
    /**
     * Parse invoice XML into structured data
     * 
     * @param string $xml Invoice XML content
     * @return array Structured invoice data
     */
    public function parseInvoiceXml($xml) {
        try {
            $dom = new DOMDocument();
            $dom->loadXML($xml);
            $xpath = new DOMXPath($dom);
            
            // Register namespaces
            $xpath->registerNamespace('fa', 'http://crd.gov.pl/wzor/2023/06/29/12648/');
            
            // Extract basic invoice data
            $invoice = [
                'ksefReferenceNumber' => $this->getXpathValue($xpath, '//fa:ReferenceNumber'),
                'entityId' => $this->entity->getId(),
                'invoiceNumber' => $this->getXpathValue($xpath, '//fa:InvoiceNumber'),
                'issueDate' => $this->getXpathValue($xpath, '//fa:IssueDate'),
                'sellerName' => $this->getXpathValue($xpath, '//fa:Seller//fa:FullName'),
                'sellerTaxId' => $this->getXpathValue($xpath, '//fa:Seller//fa:TaxId'),
                'buyerName' => $this->getXpathValue($xpath, '//fa:Buyer//fa:FullName'),
                'buyerTaxId' => $this->getXpathValue($xpath, '//fa:Buyer//fa:TaxId'),
                'totalNet' => (float) str_replace(',', '.', $this->getXpathValue($xpath, '//fa:TotalNetAmount')),
                'totalGross' => (float) str_replace(',', '.', $this->getXpathValue($xpath, '//fa:TotalGrossAmount')),
                'currency' => $this->getXpathValue($xpath, '//fa:Currency') ?: 'PLN',
                'invoiceType' => 'VAT', // Default type
                'xmlContent' => $xml,
                'items' => []
            ];
            
            // Extract invoice items
            $itemNodes = $xpath->query('//fa:InvoiceLine');
            if ($itemNodes) {
                foreach ($itemNodes as $itemNode) {
                    $item = [
                        'name' => $this->getNodeValue($xpath, 'fa:Description', $itemNode),
                        'quantity' => (float) str_replace(',', '.', $this->getNodeValue($xpath, 'fa:Quantity', $itemNode)),
                        'unit' => $this->getNodeValue($xpath, 'fa:UnitOfMeasure', $itemNode),
                        'unitPriceNet' => (float) str_replace(',', '.', $this->getNodeValue($xpath, 'fa:UnitNetPrice', $itemNode)),
                        'netValue' => (float) str_replace(',', '.', $this->getNodeValue($xpath, 'fa:NetAmount', $itemNode)),
                        'vatRate' => $this->getNodeValue($xpath, 'fa:VATRate', $itemNode),
                        'vatValue' => (float) str_replace(',', '.', $this->getNodeValue($xpath, 'fa:VATAmount', $itemNode)),
                        'grossValue' => (float) str_replace(',', '.', $this->getNodeValue($xpath, 'fa:GrossAmount', $itemNode))
                    ];
                    
                    $invoice['items'][] = $item;
                }
            }
            
            return [
                'success' => true,
                'invoice' => $invoice
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to parse invoice XML: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get XPath value from XML
     * 
     * @param DOMXPath $xpath XPath object
     * @param string $query XPath query
     * @param DOMNode $contextNode Optional context node
     * @return string Value or empty string
     */
    private function getXpathValue($xpath, $query, $contextNode = null) {
        $nodes = $contextNode ? $xpath->query($query, $contextNode) : $xpath->query($query);
        
        if ($nodes && $nodes->length > 0) {
            return $nodes->item(0)->nodeValue;
        }
        
        return '';
    }
    
    /**
     * Get node value from XML
     * 
     * @param DOMXPath $xpath XPath object
     * @param string $nodeName Node name
     * @param DOMNode $contextNode Context node
     * @return string Value or empty string
     */
    private function getNodeValue($xpath, $nodeName, $contextNode) {
        $nodes = $xpath->query($nodeName, $contextNode);
        
        if ($nodes && $nodes->length > 0) {
            return $nodes->item(0)->nodeValue;
        }
        
        return '';
    }
    
    /**
     * Send request to KSeF API
     * 
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array|null $data Request data
     * @param array $queryParams Query parameters
     * @param bool $rawResponse Return raw response instead of JSON
     * @return mixed Response data
     */
    private function sendRequest($method, $endpoint, $data = null, $queryParams = [], $rawResponse = false) {
        $url = $this->apiUrl . $endpoint;
        
        // Add query parameters
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        $headers = [
            'Accept: application/json'
        ];
        
        // Add token if available (except for authentication endpoints)
        if ($this->token && !in_array($endpoint, ['/auth/token', '/auth/login'])) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        
        // Add data if provided
        if ($data !== null) {
            $jsonData = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($jsonData);
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['error' => 'cURL Error: ' . $error];
        }
        
        if ($rawResponse) {
            return $response;
        }
        
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Invalid JSON response: ' . json_last_error_msg()];
        }
        
        if ($httpCode >= 400) {
            return [
                'error' => $decodedResponse['message'] ?? 'API Error: HTTP ' . $httpCode,
                'code' => $httpCode
            ];
        }
        
        return $decodedResponse;
    }
}
