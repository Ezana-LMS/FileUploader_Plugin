<?php 
/*
 * Created on Mon Jan 17 2022
 *
 *  Devlan - devlan.co.ke 
 *
 * hello@devlan.info
 *
 *
 * The Devlan End User License Agreement
 *
 * Copyright (c) 2022 Devlan
 *
 * 1. GRANT OF LICENSE
 * Devlan hereby grants to you (an individual) the revocable, personal, non-exclusive, and nontransferable right to
 * install and activate this system on two separated computers solely for your personal and non-commercial use,
 * unless you have purchased a commercial license from Devlan. Sharing this Software with other individuals, 
 * or allowing other individuals to view the contents of this Software, is in violation of this license.
 * You may not make the Software available on a network, or in any way provide the Software to multiple users
 * unless you have first purchased at least a multi-user license from Devlan.
 *
 * 2. COPYRIGHT 
 * The Software is owned by Devlan and protected by copyright law and international copyright treaties. 
 * You may not remove or conceal any proprietary notices, labels or marks from the Software.
 *
 * 3. RESTRICTIONS ON USE
 * You may not, and you may not permit others to
 * (a) reverse engineer, decompile, decode, decrypt, disassemble, or in any way derive source code from, the Software;
 * (b) modify, distribute, or create derivative works of the Software;
 * (c) copy (other than one back-up copy), distribute, publicly display, transmit, sell, rent, lease or 
 * otherwise exploit the Software.  
 *
 * 4. TERM
 * This License is effective until terminated. 
 * You may terminate it at any time by destroying the Software, together with all copies thereof.
 * This License will also terminate if you fail to comply with any term or condition of this Agreement.
 * Upon such termination, you agree to destroy the Software, together with all copies thereof.
 *
 * 5. NO OTHER WARRANTIES. 
 * Devlan  DOES NOT WARRANT THAT THE SOFTWARE IS ERROR FREE. 
 * Devlan SOFTWARE DISCLAIMS ALL OTHER WARRANTIES WITH RESPECT TO THE SOFTWARE, 
 * EITHER EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO IMPLIED WARRANTIES OF MERCHANTABILITY, 
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT OF THIRD PARTY RIGHTS. 
 * SOME JURISDICTIONS DO NOT ALLOW THE EXCLUSION OF IMPLIED WARRANTIES OR LIMITATIONS
 * ON HOW LONG AN IMPLIED WARRANTY MAY LAST, OR THE EXCLUSION OR LIMITATION OF 
 * INCIDENTAL OR CONSEQUENTIAL DAMAGES,
 * SO THE ABOVE LIMITATIONS OR EXCLUSIONS MAY NOT APPLY TO YOU. 
 * THIS WARRANTY GIVES YOU SPECIFIC LEGAL RIGHTS AND YOU MAY ALSO 
 * HAVE OTHER RIGHTS WHICH VARY FROM JURISDICTION TO JURISDICTION.
 *
 * 6. SEVERABILITY
 * In the event of invalidity of any provision of this license, the parties agree that such invalidity shall not
 * affect the validity of the remaining portions of this license.
 *
 * 7. NO LIABILITY FOR CONSEQUENTIAL DAMAGES IN NO EVENT SHALL DEVLAN  OR ITS SUPPLIERS BE LIABLE TO YOU FOR ANY
 * CONSEQUENTIAL, SPECIAL, INCIDENTAL OR INDIRECT DAMAGES OF ANY KIND ARISING OUT OF THE DELIVERY, PERFORMANCE OR 
 * USE OF THE SOFTWARE, EVEN IF DEVLAN HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES
 * IN NO EVENT WILL DEVLAN  LIABILITY FOR ANY CLAIM, WHETHER IN CONTRACT 
 * TORT OR ANY OTHER THEORY OF LIABILITY, EXCEED THE LICENSE FEE PAID BY YOU, IF ANY.
 */

require_once 'google-api-php-client/src/Google/Client.php';
require_once "google-api-php-client/src/Google/Service/Oauth2.php";

header('Content-Type: text/html; charset=utf-8');

// Get your app info from JSON downloaded from google dev console
$json = json_decode(file_get_contents("./conf/GoogleClientId.json"), true);

$CLIENT_ID = $json['web']['client_id'];
$CLIENT_SECRET = $json['web']['client_secret'];
$REDIRECT_URI = $json['web']['redirect_uris'][0];

// Set the scopes you need
$SCOPES = array(
	'https://www.googleapis.com/auth/drive.file',
	'https://www.googleapis.com/auth/userinfo.email',
	'https://www.googleapis.com/auth/userinfo.profile');

/**
 * Store OAuth 2.0 credentials in the application's database.
 *
 * @param String $userId User's ID.
 * @param String $credentials Json representation of the OAuth 2.0 credentials to store.
 * @param String $userInfo Overall user data
 */
function storeCredentials($userId, $credentials, $userInfo) {
	$_SESSION["userInfo"] = $userInfo;
	setcookie("userId", $userId, time() + (86400 * 30), "/");
	setcookie("credentials", $credentials, time() + (86400 * 30), "/");

	// TODO: Integrate with a database
}

/**
 * Get OAuth 2.0 credentials from the application's database.
 *
 * @param String $userId User's ID.
 * @return JSON $credentials if the user has logged in to the service before, else return null
 */
function getStoredCredentials($userId) {
	// TODO: Integrate with a database
	if(isset($_COOKIE["credentials"])) {
		return $_COOKIE["credentials"];
	}else {
		return null;
	}
}

/** 
* Lets first get an authorization URL to our client, it will forward the client to Google's Concent window
* @param String $emailAddress
* @param String $state
* @return String URL to Google Concent screen
*/
function getAuthorizationUrl($emailAddress, $state) {
	global $CLIENT_ID, $REDIRECT_URI, $SCOPES;
	$client = new Google_Client();

	$client->setClientId($CLIENT_ID);
	$client->setRedirectUri($REDIRECT_URI);
	$client->setAccessType('offline');
	$client->setApprovalPrompt('auto');
	$client->setState($state);
	$client->setScopes($SCOPES);
	$tmpUrl = parse_url($client->createAuthUrl());
	$query = explode('&', $tmpUrl['query']);
	$query[] = 'user_id=' . urlencode($emailAddress);
	
	return
	$tmpUrl['scheme'] . '://' . $tmpUrl['host'] .
	$tmpUrl['path'] . '?' . implode('&', $query);
}

/**
 * Exchange an authorization code for OAuth 2.0 credentials.
 *
 * @param String $authorizationCode Authorization code to exchange for OAuth 2.0
 *                                  credentials.
 * @return String Json representation of the OAuth 2.0 credentials.
 * @throws An error occurred. And prints the error message
 */
function exchangeCode($authorizationCode) {
	try {
		global $CLIENT_ID, $CLIENT_SECRET, $REDIRECT_URI;
		$client = new Google_Client();

		$client->setClientId($CLIENT_ID);
		$client->setClientSecret($CLIENT_SECRET);
		$client->setRedirectUri($REDIRECT_URI);
		return $client->authenticate($authorizationCode);
	} catch (Exception $e) {
		print 'An error occurred: ' . $e->getMessage();
	}
	
}

/**
 * Retrieve credentials using the provided authorization code.
 *
 * @param String authorizationCode Authorization code to use to retrieve an access token.
 * @param String state State to set to the authorization URL in case of error.
 * @return String Json representation of the OAuth 2.0 credentials.
 */
function getCredentials($authorizationCode, $state) {
	$emailAddress = '';
	try {
		$credentials = exchangeCode($authorizationCode);
		$userInfo = getUserInfo($credentials);
		$emailAddress = $userInfo->getEmail();
		$userId = $userInfo->getId();
		$credentialsArray = json_decode($credentials, true);
		if (isset($credentialsArray['refresh_token'])) {
			storeCredentials($userId, $credentials, $userInfo);
			return $credentials;
		} else {
			$credentials = getStoredCredentials($userId);
			if ($credentials != null && isset($credentials)) {
				storeCredentials($userId, $credentials, $userInfo);
			return $credentials;
		} else {
			echo "Unexpected error.";die;
		}
	}
} catch (CodeExchangeException $e) {
	print 'An error occurred during code exchange.';
		// Drive apps should try to retrieve the user and credentials for the current
		// session.
		// If none is available, redirect the user to the authorization URL.
	$e->setAuthorizationUrl(getAuthorizationUrl($emailAddress, $state));
	throw $e;
} catch (NoUserIdException $e) {
	print 'No e-mail address could be retrieved.';
}
	// No token has been retrieved.
$authorizationUrl = getAuthorizationUrl($emailAddress, $state);
}

/**
 * Send a request to the UserInfo API to retrieve the user's information.
 *
 * @param String credentials OAuth 2.0 credentials to authorize the request.
 * @return Userinfo User's information.
 * @throws NoUserIdException An error occurred.
 */
function getUserInfo($credentials) {
	$apiClient = new Google_Client();
	$apiClient->setAccessToken($credentials);
	$userInfoService = new Google_Service_Oauth2($apiClient);
	try {
		$userInfo = $userInfoService->userinfo->get();

		if ($userInfo != null && $userInfo->getId() != null) {
			return $userInfo;
		} else {
			echo "No user ID";
		}
	} catch (Exception $e) {
		print 'An error occurred: ' . $e->getMessage();
	}
	
}
