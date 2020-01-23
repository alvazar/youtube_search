<?php
namespace service;

//ini_set('display_errors',true);
//error_reporting(7);

// service handler
final class Servicer {
	public function execute(array $params=[]) {
		// prepare params
		$methodName = $params['method'] ?? '';
		$methodName = str_replace(".","_",$methodName);
		$clName = sprintf("service\Service_%s",$methodName);
		$methodParams = $params['params'] ?? [];

		//
		$result = [
			'jsonrpc' => '2.0',
			'id' => $params['id']
		];

		// check service exists
		if (!class_exists($clName)) {
			$result['error'] = ['code' => 1, 'message' => sprintf('unknow service %s',$params['method'])];
			return $result;
		}
		
		// run service
		try {
			$result += (new $clName())->execute($methodParams);
		}
		catch (\Exception $Err) {
			$result['error'] = ['code' => 2, 'message' => $Err->getMessage()];
		}
		return $result;
	}
}

//
interface IService {
	public function execute(array $params);
}

// services

// parent class for youtube services
abstract class YoutubeService {
	//
	protected $GoogleService = false;
	
	// init google lib
	protected function init() {
		// code excerpt from google sample
		if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
			throw new \Exception(sprintf('Please run "composer require google/apiclient:~2.0" in "%s"', __DIR__));
		}
		require_once __DIR__ . '/vendor/autoload.php';
		$client = new \Google_Client();
		$client->setApplicationName('test for work');
		$client->setScopes([
			'https://www.googleapis.com/auth/youtube.readonly',
		]);
		  
		//
		$client->setAuthConfig(__DIR__.'/testforwork-a782f78df8a7.json');
		$accessToken = $client->fetchAccessTokenWithAssertion();
		$client->setAccessToken($accessToken);
		
		// Define service object for making API requests.
		$this->GoogleService = new \Google_Service_YouTube($client);
	}

	// send google query
	protected function query(array $params) {
		//
		if ($this->GoogleService === false) $this->init();
		return $this->GoogleService->search->listSearch('snippet', $params);
	}
}

class Service_video_search extends YoutubeService implements IService {
	public function execute(array $params) {
		$keyword = $params['keyword'] ?? '';
		$wordLength = mb_strlen($keyword);
		$maxResults = $params['maxResults'] ?? 5;
		//
		$result = [];
		if ($wordLength < 5) {
			throw new \Exception('Название должно быть не менее 5 символов');
		}
		elseif ($wordLength > 50) {
			$keyword = mb_substr($keyword,0,50);
		}
		
		$queryParams = [
			'maxResults' => $maxResults,
			'q' => $keyword,
			'type' => 'video'
		];
		$response = $this->query($queryParams);
		
		$result['result'] = [];	
		foreach ($response->items as $item) {
			$result['result'][] = [
				'channelTitle' => $item['snippet']->channelTitle,
				'title' => $item['snippet']->title,
				'videoID' => $item->id->videoId,
				'thumbnail' => $item['snippet']->thumbnails->medium->url
			];
		}
		
		return $result;
	}
}

$videoTitle = $_GET['videoTitle'] ?? '';
$maxResults = $_GET['maxResults'] ?? 5;

$request = [
	'method' => 'video.search',
	'params' => ['keyword' => $videoTitle,'maxResults' => $maxResults],
	'id' => 1
];

$Service = new Servicer();
print json_encode($Service->execute($request));