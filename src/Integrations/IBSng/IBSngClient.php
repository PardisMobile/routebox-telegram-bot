<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use RuntimeException;

require_once __DIR__ . '/IBSngRenewal.php';

/**
 * A definitive IBSng account lookup miss. Keep this distinct from transport,
 * authentication, or malformed-response errors so callers can report it.
 */
final class IBSngUserNotFoundException extends RuntimeException
{
}

/**
 * IBSng A1.24 Free Edition Web Panel adapter.
 *
 * This adapter deliberately uses the actual A1.24 Apache Web Panel entry
 * points. RouteBox does not expose or require IBSng's internal XML-RPC port.
 */
final class IBSngClient
{
    private string $baseUrl;
    private string $cookieFile;
    private bool $loggedIn = false;

    public function __construct(
        private readonly string $host,
        private readonly string $username,
        private readonly string $password,
        private readonly int $port = 80,
        private readonly int $timeout = 30,
        private readonly string $basePath = '/IBSng',
    ) {
        if ($this->host === '' || $this->username === '' || $this->password === '') {
            throw new RuntimeException('IBSng connection settings are incomplete.');
        }
        if (!in_array($this->port, [80, 443], true)) {
            throw new RuntimeException('IBSng Web Panel port must be 80 or 443.');
        }

        $scheme = $this->port === 443 ? 'https' : 'http';
        $this->baseUrl = $scheme . '://' . $this->host . '/' . trim($this->basePath, '/');

        $cookie = tempnam(sys_get_temp_dir(), 'routebox_ibsng_');
        if ($cookie === false) {
            throw new RuntimeException('Could not create temporary IBSng session storage.');
        }
        $this->cookieFile = $cookie;
    }

    public static function defaultPort(): int
    {
        return 80;
    }

    public function __destruct()
    {
        if (isset($this->cookieFile) && is_file($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    private function request(string $path, array $post = [], bool $followLocation = true): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize IBSng HTTP connection.');
        }

        $headers = [
            'Accept: text/html,application/xhtml+xml',
            'Cache-Control: no-cache',
            'Accept-Encoding: identity',
            'Connection: close',
        ];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $followLocation,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => 'RouteBox-IBSng-A1.24/1.0',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
        ];

        if ($post !== []) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($post, '', '&');
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
        }

        curl_setopt_array($ch, $options);

        curl_setopt($ch, CURLOPT_HEADER, true);

        $rawResponse = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $responseHeaders = '';
        $responseBody = $rawResponse;

        if ($rawResponse !== false && $headerSize > 0) {
            $responseHeaders = substr($rawResponse, 0, $headerSize);
            $responseBody = substr($rawResponse, $headerSize);
        }

        curl_close($ch);

        /*
         * IBSng A1.24 on the legacy Apache/PHP stack can send the complete
         * response and then leave the HTTP connection open. Preserve the body
         * already received before libcurl reports CURLE_OPERATION_TIMEDOUT.
         */
        if (
            $errno === CURLE_OPERATION_TIMEDOUT
            && $code >= 200
            && $code < 400
            && $responseBody !== false
        ) {
            $responseBody = (string)$responseBody;
        }

        if ($responseBody === false) {
            throw new RuntimeException(
                'IBSng connection failed: ' . ($error !== '' ? $error : 'unknown cURL error')
            );
        }

        if ($code < 200 || $code >= 400) {
            throw new RuntimeException(
                'IBSng Web Panel HTTP error: ' . $code . ' (' . $effectiveUrl . ')'
            );
        }

        return [
            'body' => (string)$responseBody,
            'headers' => (string)$responseHeaders,
            'url' => $effectiveUrl,
            'code' => $code,
        ];
    }

    /**
     * Login without following IBSng's redirect.
     *
     * IBSng A1.24 returns a quick 302 to admin_index.php after a successful
     * login, but the redirected admin page can keep the legacy HTTP connection
     * open for a long time. Following that redirect makes the connection test
     * appear hung even though authentication already succeeded.
     */
    private function loginFast(): void
    {
        $response = $this->request('/admin/', [
            'username' => $this->username,
            'password' => $this->password,
        ], false);

        if ($response['code'] < 300 || $response['code'] >= 400) {
            $body = $response['body'];
            $hasLoginForm = stripos($body, 'name="username"') !== false
                && stripos($body, 'name="password"') !== false;
            if ($hasLoginForm) {
                throw new RuntimeException('IBSng authentication failed: Admin username/password were rejected.');
            }
            throw new RuntimeException('IBSng authentication failed: unexpected login response.');
        }

        $this->loggedIn = true;
    }

    private function ensureLogin(): void
    {
        if ($this->loggedIn) {
            return;
        }

        $this->loginFast();
    }

    private function textFromHtml(string $html): string
    {
        return trim(preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        ) ?? '');
    }

    private function firstMatch(string $pattern, string $html): ?string
    {
        return preg_match($pattern, $html, $m) === 1
            ? trim(html_entity_decode((string)($m[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            : null;
    }

    public function testConnection(): void
    {
        /*
         * Fast connectivity/authentication test only. Do NOT request Group List
         * or any other slow IBSng page here. The successful A1.24 login itself
         * is the health check.
         */
        $this->loginFast();
    }

    public function listGroups(): array
    {
        $this->ensureLogin();
        $html = $this->request('/admin/group/group_list.php')['body'];
        $groups = [];

        if (preg_match_all(
            '/group_info\.php\?group_name=([^"&<>\s]+)/i',
            $html,
            $matches
        )) {
            foreach ($matches[1] as $encoded) {
                $name = urldecode(html_entity_decode((string)$encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($name !== '') {
                    $groups[$name] = $name;
                }
            }
        }

        return array_values($groups);
    }

    public function getGroupInfo(string $groupName): array
    {
        $this->ensureLogin();
        $html = $this->request('/admin/group/group_info.php?group_name=' . rawurlencode($groupName))['body'];

        $groupId = $this->firstMatch(
            "/name=[\"']group_id[\"'][^>]*value=[\"']([^\"']+)[\"']/i",
            $html
        );
        if ($groupId === null) {
            $groupId = $this->firstMatch(
                '/(?:Group\s+ID|group_id)\s*(?:<[^>]+>\s*)+([0-9]+)/i',
                $html
            );
        }

        return ['group_name' => $groupName, 'group_id' => $groupId, 'html' => $html];
    }

    /**
     * Read a displayed value from the IBSng A1.24 user-information page using
     * the same label/value-cell structure as the verified reference parser.
     */
    private function displayedLabelValue(string $html, string $wantedLabel): ?string
    {
        if (!class_exists('DOMDocument')) {
            throw new RuntimeException('PHP DOM extension is required to parse IBSng A1.24 user information.');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new \\DOMDocument();
            $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \\DOMXPath($dom);
            $cells = $xpath->query('//td');
            if (!$cells) {
                return null;
            }

            foreach ($cells as $cell) {
                if (!$cell instanceof \\DOMElement) {
                    continue;
                }
                $label = trim($this->textFromHtml($cell->textContent), " \\t\\n\\r\\0\\x0B:");
                if (strcasecmp($label, $wantedLabel) !== 0) {
                    continue;
                }
                $row = $cell->parentNode;
                if (!$row instanceof \\DOMElement || strtolower($row->tagName) !== 'tr') {
                    continue;
                }

                $seenLabel = false;
                foreach ($row->childNodes as $sibling) {
                    if (!$sibling instanceof \\DOMElement || strtolower($sibling->tagName) !== 'td') {
                        continue;
                    }
                    if ($sibling->isSameNode($cell)) {
                        $seenLabel = true;
                        continue;
                    }
                    if (!$seenLabel) {
                        continue;
                    }
                    $class = $sibling->getAttribute('class');
                    if (
                        strpos($class, 'Form_Content_Row_Right') === false
                        && strpos($class, 'Form_Content_Row_Right_Textarea') === false
                    ) {
                        continue;
                    }

                    $textarea = $xpath->query('.//textarea', $sibling);
                    if ($textarea && $textarea->length > 0) {
                        return $this->textFromHtml((string)$textarea->item(0)->textContent);
                    }
                    $select = $xpath->query('.//select', $sibling);
                    if ($select && $select->length > 0) {
                        $selected = $xpath->query('.//option[@selected]', $select->item(0));
                        return $this->textFromHtml((string)(($selected && $selected->length > 0)
                            ? $selected->item(0)->textContent
                            : $select->item(0)->textContent));
                    }
                    $input = $xpath->query('.//input[not(@type="checkbox") and not(@type="hidden")]', $sibling);
                    if ($input && $input->length > 0 && $input->item(0) instanceof \\DOMElement) {
                        return $this->textFromHtml($input->item(0)->getAttribute('value'));
                    }
                    return $this->textFromHtml($sibling->textContent);
                }
            }
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Extract the value associated with an IBSng attribute checkbox. This
     * follows the nested-table traversal used by the A1.24 reference script.
     */
    private function attributeValue(string $html, string $attributeName): ?string
    {
        if (!class_exists('DOMDocument')) {
            throw new RuntimeException('PHP DOM extension is required to parse IBSng A1.24 user information.');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new \\DOMDocument();
            $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \\DOMXPath($dom);
            $checkboxes = $xpath->query('//input[starts-with(@name, "attr_edit_checkbox_")]');
            if (!$checkboxes) {
                return null;
            }

            foreach ($checkboxes as $checkbox) {
                if (!$checkbox instanceof \\DOMElement
                    || trim($checkbox->getAttribute('value')) !== $attributeName) {
                    continue;
                }

                $rows = $xpath->query('ancestor::tr', $checkbox);
                if (!$rows || $rows->length === 0) {
                    continue;
                }

                $valueCell = null;
                $bestScore = -1;
                foreach ($rows as $row) {
                    $cells = $xpath->query('./td', $row);
                    if (!$cells || $cells->length < 2) {
                        continue;
                    }

                    $checkboxCellIndex = -1;
                    $labelCell = null;
                    foreach ($cells as $index => $cell) {
                        $matches = $xpath->query(
                            './/input[@name="' . $checkbox->getAttribute('name') . '"]',
                            $cell
                        );
                        if ($matches && $matches->length > 0) {
                            $checkboxCellIndex = (int)$index;
                            $labelCell = $cell;
                            break;
                        }
                    }
                    if ($checkboxCellIndex < 0 || !$labelCell) {
                        continue;
                    }

                    for ($i = $checkboxCellIndex + 1; $i < $cells->length; $i++) {
                        $cell = $cells->item($i);
                        $class = $cell instanceof \\DOMElement ? $cell->getAttribute('class') : '';
                        if (
                            strpos($class, 'Form_Content_Row_Right') === false
                            && strpos($class, 'Form_Content_Row_Right_Textarea') === false
                        ) {
                            continue;
                        }
                        $score = strpos($labelCell->getAttribute('class'), 'Form_Content_Row_Left') !== false ? 120 : 100;
                        if ($score > $bestScore) {
                            $bestScore = $score;
                            $valueCell = $cell;
                        }
                        break;
                    }
                }

                if (!$valueCell) {
                    return null;
                }

                $textarea = $xpath->query('.//textarea', $valueCell);
                if ($textarea && $textarea->length > 0) {
                    return $this->textFromHtml((string)$textarea->item(0)->textContent);
                }
                $select = $xpath->query('.//select', $valueCell);
                if ($select && $select->length > 0) {
                    $selected = $xpath->query('.//option[@selected]', $select->item(0));
                    return $this->textFromHtml((string)(($selected && $selected->length > 0)
                        ? $selected->item(0)->textContent
                        : $select->item(0)->textContent));
                }
                $input = $xpath->query('.//input[not(@type="checkbox") and not(@type="hidden")]', $valueCell);
                if ($input && $input->length > 0 && $input->item(0) instanceof \\DOMElement) {
                    return $this->textFromHtml($input->item(0)->getAttribute('value'));
                }
                return $this->textFromHtml($valueCell->textContent);
            }
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Fetch and normalize the verified account state required by the renewal
     * policy. Nearest Expiration Date is read as IBSng displays it; dates are
     * never guessed from Relative/Absolute Expiration labels.
     *
     * @return array{user_id:string,expired:bool,remaining_days:int,first_login:?string,nearest_exp_date:?string}
     */
    public function getUserRenewalStateByUsername(string $username): array
    {
        $info = $this->getUserInfoByUsername($username);
        $html = (string)$info['html'];
        $userId = trim((string)($info['user_id'] ?? ''));
        if ($userId === '') {
            throw new IBSngUserNotFoundException('IBSng user was not found.');
        }

        $firstLogin = trim((string)($this->attributeValue($html, 'first_login') ?? ''));
        $relativeExpiration = trim((string)($this->attributeValue($html, 'rel_exp') ?? ''));
        $nearestExpiration = trim((string)($this->displayedLabelValue($html, 'Nearest Expiration Date') ?? ''));

        $emptyValues = ['', '---------------', 'N/A', '-'];
        $firstLoginValue = in_array($firstLogin, $emptyValues, true) ? null : $firstLogin;
        $nearestValue = in_array($nearestExpiration, $emptyValues, true) ? null : $nearestExpiration;

        // An account that has not had its First Login yet has not started its
        // relative-expiration countdown. Preserve the configured remaining
        // relative duration when renewing such an account.
        if ($firstLoginValue === null && $nearestValue === null) {
            if (!preg_match('/^\\s*(\\d+)\\s*(?:days?|روز)?\\s*$/iu', $relativeExpiration, $m)) {
                throw new RuntimeException('IBSng account expiry state could not be verified from its user-information page.');
            }
            return [
                'user_id' => $userId,
                'expired' => false,
                'remaining_days' => (int)$m[1],
                'first_login' => null,
                'nearest_exp_date' => null,
            ];
        }

        if ($nearestValue === null) {
            throw new RuntimeException('IBSng nearest expiration date is missing; renewal was stopped safely.');
        }

        $expiryTimestamp = strtotime($nearestValue);
        if ($expiryTimestamp === false) {
            throw new RuntimeException('IBSng nearest expiration date could not be parsed; renewal was stopped safely.');
        }

        $remainingSeconds = $expiryTimestamp - time();
        $expired = $remainingSeconds <= 0;
        $remainingDays = $expired ? 0 : (int)ceil($remainingSeconds / 86400);

        return [
            'user_id' => $userId,
            'expired' => $expired,
            'remaining_days' => $remainingDays,
            'first_login' => $firstLoginValue,
            'nearest_exp_date' => $nearestValue,
        ];
    }

    public function getUserInfoByUsername(string $username): array
    {
        $this->ensureLogin();
        $html = $this->request('/admin/user/user_info.php?normal_username=' . rawurlencode($username))['body'];

        $plain = $this->textFromHtml($html);
        $loginPage = stripos($html, 'name="username"') !== false
            && stripos($html, 'name="password"') !== false;
        if ($loginPage) {
            $this->loggedIn = false;
            $this->ensureLogin();
            $html = $this->request('/admin/user/user_info.php?normal_username=' . rawurlencode($username))['body'];
            $plain = $this->textFromHtml($html);
        }

        if (stripos($plain, 'User Information') === false
            && stripos($plain, 'User Informations') === false
            && stripos($plain, 'Username') === false
            && stripos($plain, 'Normal Username') === false) {
            throw new RuntimeException('IBSng user was not found or the Web Panel returned an unexpected page.');
        }

        $result = ['username' => $username, 'html' => $html, 'text' => $plain];
        if (($uid = $this->firstMatch(
            "/name=[\"']user_id[\"'][^>]*value=[\"']([^\"']+)[\"']/i",
            $html
        )) !== null) {
            $result['user_id'] = $uid;
        }

        return $result;
    }

    public function createUser(string $ispName, string $groupName, int $credit = 0): array
    {
        $this->ensureLogin();

        /*
         * IBSng A1.24 creates the user and responds with HTTP 302.
         * The new user ID is placed in the Location header, for example:
         *
         * /IBSng/admin/plugins/edit.php?edit_user=1&user_id=31375&count=1...
         *
         * We deliberately do NOT follow the redirect here because the
         * redirected edit page may keep the legacy Apache connection open.
         */
        $response = $this->request(
            '/admin/user/add_new_users.php',
            [
                'count' => 1,
                'credit' => $credit,
                'owner_name' => $this->username,
                'group_name' => $groupName,
                'credit_comment' => '',
            ],
            false
        );

        $userId = $this->firstMatch(
            '/[?&]user_id=([0-9]+)/i',
            $response['headers'] ?? ''
        );

        if ($userId === null) {
            $userId = $this->firstMatch(
                '/[?&]user_id=([0-9]+)/i',
                $response['url']
            );
        }

        /*
         * Some IBSng installations may return the redirect URL in the body
         * instead of the effective URL. Keep a fallback for that case.
         */
        if ($userId === null) {
            $userId = $this->firstMatch(
                '/[?&]user_id=([0-9]+)/i',
                $response['body']
            );
        }

        if ($userId === null) {
            throw new RuntimeException(
                'IBSng user creation succeeded but no user_id was found. '
                . 'HTTP code=' . (string)$response['code']
                . ' URL=' . $response['url']
            );
        }

        return [
            'user_ids' => [$userId],
            'user_id' => $userId,
        ];
    }

    public function setUserCredentials(string|int $userId, string $username, string $password): array
    {
        $this->ensureLogin();

        if ($username === '' || $password === '') {
            throw new RuntimeException(
                'IBSng username and password cannot be empty.'
            );
        }

        /*
         * IBSng A1.24 requires the user's edit page to be opened first.
         * This is the same flow used by the working manual test:
         *
         * GET:
         * /admin/plugins/edit.php?edit_user=1&user_id=...
         *
         * POST:
         * edit_tpl_cs=normal_username
         * attr_update_method_0=normalAttrs
         */
        $this->request(
            '/admin/plugins/edit.php?edit_user=1&user_id='
            . rawurlencode((string)$userId),
            [],
            false
        );

        $response = $this->request(
            '/admin/plugins/edit.php',
            [
                'update' => '1',
                'edit_tpl_cs' => 'normal_username',
                'target' => 'user',
                'target_id' => (string)$userId,
                'user_id' => (string)$userId,
                'has_normal_username' => 't',
                'current_normal_username' => '',
                'normal_username' => $username,
                'password' => $password,
                'normal_save_user_add' => 't',
                'attr_update_method_0' => 'normalAttrs',
            ],
            false
        );

        $body = $response['body'];
        $text = $this->textFromHtml($body);

        /*
         * Do not treat every occurrence of "error" as failure.
         * IBSng pages can contain that word in JavaScript or unrelated text.
         */
        if (
            stripos($text, 'normal username') !== false
            && stripos($text, 'error') !== false
        ) {
            throw new RuntimeException(
                'IBSng rejected the Internet Username/password update.'
            );
        }

        return [
            'updated' => true,
            'user_id' => (string)$userId,
            'username' => $username,
            'html' => $body,
            'text' => $text,
            'url' => $response['url'],
            'code' => $response['code'],
        ];
    }

    /**
     * Apply the IBSng A1.24 edit-user attributes through the same Web Panel
     * endpoint used by the native edit form. Caller supplies the verified
     * renewal attributes; this method never edits Absolute Expiration.
     *
     * IBSng A1.24 routes edit form updates through edit.php -> attrs.php ->
     * UpdateAttrsHelper -> user.updateUserAttrs.
     */
    /** Low-level IBSng A1.24 edit-user request; renewal policy lives in IBSngRenewal. */
    public function submitRenewalAttributes(
        string|int $userId,
        string $username,
        ?int $relativeExpiration,
        bool $resetFirstLogin,
        string $comment,
        string|int $groupId,
        string $groupName
    ): array {
        // Keep the pre-renewal two-argument contract non-fatal for old callers.
        // The legacy flow was not enabled; it must not accidentally mutate IBSng.
        if (
            $username === ''
            || ($relativeExpiration === null && !$resetFirstLogin)
            || (string)$groupId === ''
            || trim($groupName) === ''
        ) {
            throw new RuntimeException(
                'IBSng renewal must be invoked through the centralized IBSngRenewal module with verified account state.'
            );
        }

        $this->ensureLogin();

        $editTemplates = [];
        $methods = [];
        $post = [
            'update' => '1',
            'target' => 'user',
            'target_id' => (string)$userId,
            'user_id' => (string)$userId,
            // IBSng A1.24 attrs.php requires these context fields on every
            // edit-user attribute update; they are not optional form metadata.
            'group_id' => (string)$groupId,
            'group_name' => $groupName,
            'owner_name' => $this->username,
            'comment' => $comment,
        ];

        // The normal IBSng Relative Expiration edit plugin uses has_rel_exp
        // to decide whether rel_exp is updated or deleted.
        if ($relativeExpiration !== null) {
            $editTemplates[] = 'rel_exp';
            $methods[] = 'relExp';
            $post['has_rel_exp'] = 't';
            $post['rel_exp'] = (string)$relativeExpiration;
        }

        // IBSng's native First Login control is a reset checkbox. Only send
        // this flag for expired accounts; active accounts retain First Login.
        if ($resetFirstLogin) {
            $editTemplates[] = 'first_login';
            $methods[] = 'firstLogin';
            $post['reset_first_login'] = 't';
        }

        if ($editTemplates === []) {
            throw new RuntimeException('No IBSng renewal attributes were selected.');
        }

        $post['edit_tpl_cs'] = implode(',', $editTemplates);
        foreach ($methods as $index => $method) {
            $post['attr_update_method_' . $index] = $method;
        }

        $this->request(
            '/admin/plugins/edit.php?edit_user=1&user_id=' . rawurlencode((string)$userId),
            [],
            false
        );
        $response = $this->request('/admin/plugins/edit.php', $post, false);
        $text = $this->textFromHtml($response['body']);

        /*
         * A1.24 edit_funcs.php calls updateTargetAttrs(FALSE): successful
         * updates redirect to the target user info page; failed updates render
         * the edit form again. Do not infer success from a generic body string.
         */
        $location = $this->firstMatch('/^Location:\s*([^\r\n]+)/mi', $response['headers']);
        $redirectQuery = [];
        if ($location !== null) {
            parse_str((string)parse_url($location, PHP_URL_QUERY), $redirectQuery);
        }
        if (
            $response['code'] < 300
            || $response['code'] >= 400
            || $location === null
            || stripos($location, 'user_info.php') === false
            || (string)($redirectQuery['user_id'] ?? '') !== (string)$userId
        ) {
            throw new RuntimeException(
                'IBSng did not redirect to the expected user information page after the renewal update.'
            );
        }

        return [
            'updated' => true,
            'user_id' => (string)$userId,
            'username' => $username,
            'relative_expiration' => $relativeExpiration,
            'first_login_reset' => $resetFirstLogin,
            'comment' => $comment,
            'text' => $text,
            'url' => $response['url'],
            'code' => $response['code'],
        ];

    }

    /**
     * Compatibility entry point. Renewal implementation is delegated to the
     * dedicated IBSngRenewal module; existing non-renewal client behavior stays unchanged.
     */
    public function renewUser(
        string|int $userId,
        string $username = '',
        ?int $relativeExpiration = null,
        bool $resetFirstLogin = false,
        string $comment = '',
        string|int $groupId = '',
        string $groupName = ''
    ): array {
        return IBSngRenewal::executeClientRenewal(
            $this,
            $userId,
            $username,
            $relativeExpiration,
            $resetFirstLogin,
            $comment,
            $groupId,
            $groupName
        );
    }

    public function changeUserGroup(string|int $userId, string $groupName): mixed
    {
        throw new RuntimeException('IBSng group changes are not enabled in this first Web Panel adapter yet.');
    }
}
