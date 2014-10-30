<?php 
require_once 'google-api-php-client/src/Google/Client.php';
require_once "google-api-php-client/src/Google/Service/Oauth2.php";

header('Content-Type: text/html; charset=utf-8');

// Get your app info from JSON downloaded from google dev console
$json = json_decode(file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/conf/GoogleClientId.json"), true);

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
	$_SESSION["userId"] = $userId;
	$_SESSION["credentials"] = $credentials;
	$_SESSION["userInfo"] = $userInfo;

	// TODO: Integrate with a database
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
	$client->setApprovalPrompt('force');
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
		$_GET['code'] = $authorizationCode;
		return $client->authenticate($authorizationCode);
	} catch (Exception $e) {
		print 'An error occurred: ' . $e->getMessage();
	}
	
}

/**
 * Retrieve credentials using the provided authorization code.
 *
 * This function exchanges the authorization code for an access token and
 * queries the UserInfo API to retrieve the user's e-mail address. If a
 * refresh token has been retrieved along with an access token, it is stored
 * in the application database using the user's e-mail address as key. If no
 * refresh token has been retrieved, the function checks in the application
 * database for one and returns it if found or throws a NoRefreshTokenException
 * with the authorization URL to redirect the user to.
 *
 * @param String authorizationCode Authorization code to use to retrieve an access
 *                                 token.
 * @param String state State to set to the authorization URL in case of error.
 * @return String Json representation of the OAuth 2.0 credentials.
 * @throws An error occurred. And prints the error message
 */
function getCredentials($authorizationCode, $state) {
	$emailAddress = '';
	try {
		$credentials = exchangeCode($authorizationCode);
		
		// Get the user data
		$userInfo = getUserInfo($credentials);
		$emailAddress = $userInfo->getEmail();
		$userId = $userInfo->getId();
		$credentialsArray = json_decode($credentials, true);
		if (isset($credentialsArray['refresh_token'])) {
			
			// Save the user data
			storeCredentials($userId, $credentials, $userInfo);
			return $credentials;
		}
	} catch (Exception $e) {
		print 'An error occurred during code exchange. ' . $e->getMessage();
	} catch (Exception $e) {
		print 'No e-mail address could be retrieved.' . $e->getMessage();
	}
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
	} catch (Exception $e) {
		print 'An error occurred: ' . $e->getMessage();
	}
	if ($userInfo != null && $userInfo->getId() != null) {
		return $userInfo;
	} else {
		echo "No user ID";
	}
}
?>