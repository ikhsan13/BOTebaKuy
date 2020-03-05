<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Webhook extends CI_Controller {

	private $user;

	function __construct()
	{
		parent::__construct();
		$this->load->model('line_model');
	}

	public function index()
	{

		// if it is not POST request, just say hello
		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			die("Hi Kak. Salam Kenal Aku Bot Buatan Ikhsan Andriyawan");

		$body = file_get_contents('php://input');
		$this->line_model->writeLog($body);
		
		$bodyArray = json_decode($body, true);
		$events = $bodyArray['events'];

		foreach ($events as $event) {
			// $event['type']
			// $event['replyToken']
			// $event['source']['userId']
			// $event['source']['type']
			// $event['timestamp']
			// $event['message']['type']
			// $event['message']['id']
			// $event['message']['text']
			
			// get userdata before doing any response
			$this->user = $this->line_model->getUser($event['source']['userId']);
			
			// bila eventnya bertipe message
			if($event['type'] == 'message') 
			{
				// if message is text
				switch($event['message']['type'])
				{
					case 'text':
						$this->responseTextMessage($event);
						break;
					case 'sticker':
						$this->responseStickerMessage($event);
						break;
					// another case?

					default: continue;
				}
			} 

			// when user follow (add friend or unblock) the bot
			else if($event['type'] == 'follow')
			{
				$this->responseFollowEvent($event);
			}

			// when user unfollow (block friend) the bot
			else if($event['type'] == 'unfollow')
			{
				echo $this->line_model->resetUser($this->user['uid']);
			} 
		}
	}

	function responseTextMessage($event)
	{
		$text = trim(strtolower($event['message']['text']));

		// if user starting the game
		if($this->user['state'] < 1)
		{
			// if message is not 'mulai'
			if(strpos('mulai', $text) === FALSE)
			{
				$this->line_model->pushStickerMessage($this->user['uid'], 2, 152);
				$this->line_model->pushTextMessage($this->user['uid'], 'ketik "mulai" atau klik tombol Mulai di MENU dulu Ya Kak😉');
				return;

			} else {
				// update state to 3, means he start game and has 3 remain lives
				$this->line_model->updateState($this->user['uid'], 3);

				// generate the answer
				$question = $this->line_model->generateAnswer($this->user['uid'], $this->user['next_question']);

				// send question
				$this->line_model->pushTextMessage($this->user['uid'], "Soal: " . $question['hint']);
			}
		}

		// for next state, user has his own question placed in $this->user['answer']
		else {
			if($text == $this->user['answer'])
			{
				if($this->user['state'] == 3)
				{
					$this->line_model->pushStickerMessage($this->user['uid'], 1, 403);
					$this->line_model->pushTextMessage($this->user['uid'], "*Nani!!!!* Bisa Langsung Kejawab DOONG?!😱😱😱😱😱😱😱😱😱");
					$this->line_model->pushStickerMessage($this->user['uid'], 1, 411);
					$this->line_model->pushTextMessage($this->user['uid'], "😡😡😡OTW laporan ini mah sama pembuat bot, *Ikhsan Andriyawan* soalnya terlalu mudah Langsung kejawab lohhhh sama kak {$this->user['nama']}, ini!");
				} else if($this->user['state'] == 2) {
					$this->line_model->pushStickerMessage($this->user['uid'], 1, 13);
					$this->line_model->pushTextMessage($this->user['uid'], "Hah, yah ketebak dahh😂. Kali ini jawaban kakak BENAR.");
				} else {
					$this->line_model->pushStickerMessage($this->user['uid'], 2, 525);
					$this->line_model->pushTextMessage($this->user['uid'], "Yeaaaaaayyyyy Sampai Terharuuu aku, akhirnya bener jugakkk! Selamattttt! kak {$this->user['nama']}");
				}

				// reset to 0
				$this->line_model->updateState($this->user['uid'], 0);
				$this->line_model->pushStickerMessage($this->user['uid'], 1, 406);
				$this->line_model->pushTextMessage($this->user['uid'], 'Mau coba lagi? kali ini aku tantang kasih soal yang lebih sulit lagi! Ketik "mulai" atau klik tombol mulai di menu kapanpun Kamu siap. Kalo berani!😎');

			} else {
				if($this->user['state'] == 3)
				{
					$this->line_model->pushStickerMessage($this->user['uid'], 1, 100);
					$this->line_model->pushTextMessage($this->user['uid'], "Ow ow oww.. kurang tepat. Kesempatan menebak 2 kali lagi eaa");
				} else if($this->user['state'] == 2) {
					$this->line_model->pushStickerMessage($this->user['uid'], 1, 403);
					$this->line_model->pushTextMessage($this->user['uid'], "No. Masih salah. Ayo, satu kesempatan menebak lagi. Pikirkan baik-baik!");
				} else {
					$this->line_model->pushStickerMessage($this->user['uid'], 1, 100);
					$this->line_model->pushTextMessage($this->user['uid'], "Hahaha.. masa gak tau sihh kak {$this->user['nama']}, masih kalah nih sama mimin BOT Jawabannya itu harusnya *{$this->user['answer']}!*");
										$this->line_model->pushStickerMessage($this->user['uid'], 1, 405);
					$this->line_model->pushTextMessage($this->user['uid'], 'Penasaran? masih mau lanjut, yakin bisa jawab?. Yang barusan aja kagak🤣🤣🤣. Tapi kalo masih penasaran, ketik "mulai"! atau klik tombol mulai di menu');
				}

				// update user state
				$this->line_model->updateState($this->user['uid']);
			}
		}

	}

	function responseStickerMessage($event)
	{
		$this->line_model->pushStickerMessage($event['source']['userId'], $event['message']['packageId'], $event['message']['stickerId']);

	}

	function responseFollowEvent($event)
	{
		$user = $this->line_model->saveUser($event['source']['userId']);
		$this->line_model->pushTextMessage($user['uid'], "Halo {$user['nama']}, salam kenal!");
		$this->line_model->pushTextMessage($user['uid'], "Pada game ini Kamu diminta untuk menebak kata apa yang aku maksud. Aku akan memberikan satu petunjuk yang mengarah ke jawaban yang dimaksud. Kamu punya kesempatan maksimal 3 kali untuk menebak.");
		$this->line_model->pushTextMessage($user['uid'], 'Untuk memulai silakan ketikkan perintah "mulai"');
	}

	function coba()
	{
		$data = json_decode(file_get_contents('./questions.json'), true);

		print_r($data);
	}


}
