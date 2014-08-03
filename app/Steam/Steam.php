<?php
namespace Steam;

use \Cache as Cache;
/**
 * Steam Class
 *
 * Handles all of interactions with STEAM API except for login
 */
Class Steam {
  /**
   * Valve's Steam Web API. Register for one at http://steamcommunity.com/dev/apikey
   * @var String
   */
  protected static $API = "05F6C841BF378CA11A60B3BF1F6AA8D5";

  /**
   * Time in seconds before profile is called to an update
   * @var Integer
   */
  protected static $UPDATE_TIME = 3600; // 1 HOUR

  /**
   * Check to see if the profile's last update was long enough for new update
   * @param  Integer $updated_at last time updated
   * @return Boolean
   */
  public static function canUpdate($smallId) {
    if(Cache::has("profile_$smallId")) {
      if(Cache::get("profile_$smallId") + self::$UPDATE_TIME > time()) {
        return false;
      }
    }
    return true;
  }

  public static function setUpdate($smallId) {
    Cache::put("profile_$smallId", time(), self::$UPDATE_TIME / 60);
    return;
  }

  /**
   * Conversion of Steam3 ID to smaller number to work easier with
   * @param Integer $steam3Id
   *
   * @return Integer/Array
   */
  public static function toSmallId($steam3Id = null)
  {
    if($steam3Id && is_numeric($steam3Id)) {
      $steam3Id .= '';
      return bcsub($steam3Id,'76561197960265728');
    }

    return Array('type' => 'error',
                 'data' => 'Parameter was empty or NaN');
  }

  /**
   * Conversion of smaller steam3 ID to its regular number to work easier with
   * @param Integer $smallId
   *
   * @return Integer/Array
   */
  public static function toBigId($smallId = null)
  {
    if($smallId && is_numeric($smallId)) {
      $smallId .= '';
      return explode('.', bcadd($smallId,'76561197960265728'))[0];
    }

    return Array('type' => 'error',
                 'data' => 'Parameter was empty or NaN');
  }

  /**
   * Converts from Steam3 ID to Steam2 ID
   * @param  Integer $steam3Id
   *
   * @return String/Array
   */
  public static function toSteam2Id($steam3Id = null)
  {
    if($steam3Id && is_numeric($steam3Id)) {
      $steamIdPartOne = (substr($steam3Id,-1)%2 == 0) ? 0 : 1;
      $steamIdPartTwo = bcsub($steam3Id,'76561197960265728');
      if (bccomp($steamIdPartTwo,'0') == 1) {
        $steamIdPartTwo = bcsub($steamIdPartTwo, $steamIdPartOne);
        $steamIdPartTwo = bcdiv($steamIdPartTwo, 2);
        return "STEAM_0:$steamIdPartOne:".explode('.', $steamIdPartTwo)[0];
      }
    }

    return Array('type' => 'error',
                 'data' => 'Parameter was empty or NaN');
  }

  /**
   * Using cURL to request to Steam API Servers
   * @param  String $type ('info', 'friends', 'ban', 'alias', 'xmlInfo')
   * @param  String/Array $value
   *
   * @return Object
   */
  public static function cURLSteamAPI($type = null, $value = null) {

    // Maybe it should have default type...?
    if($type == null || $value == null) return false;

    $json = true;
    $steamAPI = self::$API;

    // So this url doesn't float in some files as many different url's
    // keeping them in one place
    switch($type) {
      // Get most of all public information about this steam user
      case 'info':
        if(is_array($value)) {
          $value = implode(',', $value);
        }
        $url = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key={$steamAPI}&steamids={$value}&".time();
        break;

      // Get list of friends (Profile must not be private)
      case 'friends':
        if(is_array($value)) {
          $value = $value[0];
        }
        $url = "http://api.steampowered.com/ISteamUser/GetFriendList/v0001/?key={$steamAPI}&steamid={$value}&relationship=friend&".time();
        break;

      // Get more detailed information about this person's ban status
      case 'ban':
        if(is_array($value)) {
          $value = implode(',', $value);
        }
        $url = "http://api.steampowered.com/ISteamUser/GetPlayerBans/v1/?key={$steamAPI}&steamids={$value}&".time();
        break;

      // Get list of usernames this user has used
      case 'alias':
        if(is_array($value)) {
          $value = $value[0];
        }
        $url = "http://steamcommunity.com/profiles/{$value}/ajaxaliases?".time();
        break;

      // For checking to make sure a user exists by this profile name
      case 'xmlInfo':
        if(is_array($value)) {
          $value = $value[0];
        }
        $url = "http://steamcommunity.com/id/{$value}/?xml=1&".time();
        $json = false;
        break;
    }


    $ch = curl_init();

    curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

    try {
      $data = curl_exec($ch);
    } catch(Exception $e) {
      return (object) array('type' => 'error',
                            'data' => 'api_conn_err');
    }
    curl_close($ch);

    if($json) {
      $data = json_decode($data);
      if(!is_object($data) && !is_array($data)) {
        return (object) array('type' => 'error',
                              'data' => 'api_data_err');
      }
    } else {
      // Still not possible to send request to valve to check by steam profile id via Steam web API :'(
      try {
        $data = simplexml_load_string($data);
        if(!is_object($data) && !is_array($data)) {
          return (object) array('type' => 'error',
                                'data' => 'api_data_err');
        }
      } catch(Exception $e) {
        return (object) array('type' => 'error',
                              'data' => 'api_data_err');
      }
    }
    return $data;
  }

}
