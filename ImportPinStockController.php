<?php

class ImportPinStockController extends \BaseController {
	/**
	 * The layout that should be used for responses.
	 */
	protected $layout = 'layouts.master';
	
	/**
	 * Show the form for creating a new resource.
	 * GET /pinstock/create
	 *
	 * @return Responsene
	 */
	
	public function randomPassword() {
	    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
	    $pass = array(); //remember to declare $pass as an array
	    $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
	    for ($i = 0; $i < 8; $i++) {
	        $n = rand(0, $alphaLength);
	        $pass[] = $alphabet[$n];
	    }
	    return implode($pass); //turn the array into a string
	}

	public function importPin() {
		$title       = '';
		$operator    = '';
		$price = '';
		$provider = '';
		$operators = array('1'=>'Viettel','2'=>'Mobilephone', '3'=>'Vinaphone', '4'=>'Gmobile', '5'=>'Vietnammobile');
		$prices = array('1'=>'10000','2'=>'20000', '3'=>'30000', '4'=>'50000', '5'=>'100000','6'=>'300000','7'=>'500000');
		$providers = array('1'=>'VinaTopup','2'=>'VTC', '3'=>'Esales', '4'=>'VNPTEpay', '5'=>'VNPay','6' => 'FormatFile.txt');

		$update_date = '';
		$expired_date = '';
		$remark      = '';
		$this->layout->content = View::make('pin-stock.import_pin', compact('operators', 'prices', 'providers', 'title', 'operator', 'price', 'provider','update_date', 'expired_date', 'remark'));
	}
	public function confirmUploadPin(){
		/* Turn a human readable passphrase
		 * into a reproducable iv/key pair
		 */
		
		$operators = array('1'=>'Viettel','2'=>'Mobilephone', '3'=>'Vinaphone', '4'=>'Gmobile', '5'=>'Vietnammobile');
		$prices = array('1'=>'10000','2'=>'20000', '3'=>'30000', '4'=>'50000', '5'=>'100000','6'=>'300000','7'=>'500000');
		$providers = array('1'=>'VinaTopup','2'=>'VTC', '3'=>'Esales', '4'=>'VNPTEpay', '5'=>'VNPay','6' => 'FormatFile.txt');

		$input = Input::all();

		$staffId = Auth::user()->id;
		
		$rules = array(
			'title'        => 'required',
			'expired_date' => 'required',
			'operator__id' => 'required',
			'price__id' => 'required',
			'avatar'       => 'required',
			'remark'       => 'required|max:255'
		);

		$msgs = array();
        $validation = Validator::make($input, $rules);
		if (!$validation->passes()) 
		{
			return Redirect::back()
            ->withInput()
            ->withErrors($validation)
            ->with('msgs', $msgs);
		} else {
			// Check space in file name upload
            $getFileName = Input::file('avatar')->getClientOriginalName();
            if(str_contains($getFileName, ' ')){

                $msg['msg'] = 'File name contents space';
                $msg['type'] = 'error';
                array_push($msgs,$msg);

                return Redirect::back()
                    ->withInput()
                    ->with('msgs', $msgs);
            }
        }
		
		$input['staff__id'] = $staffId;
		
		unset($input['_token']);

		//----------------upload----------------------
		$file            = Input::file('avatar');
		$destinationPath = public_path().'/images/import_pin/';
		$millisecond     = round(microtime(true)*1000);
		$filename        = 'file'.$millisecond.'_'.str_random(2) . '_' . $file->getClientOriginalName();
		$extension       = $file->getClientOriginalExtension();

		// Check file not format .txt
		if ($extension != 'xls' && $extension != 'txt' && $extension != 'xlsx') {
			$msg = array('type'=>'error','msg'=>'File Extension = '.$extension.' Was Not Allowed');
			array_push($msgs,$msg);
			return Redirect::back()
		            ->withInput()
		            ->withErrors($validation)
		            ->with('msgs', $msgs);
		}
		
        $uploadSuccess = $file->move($destinationPath, $filename);

        if ($uploadSuccess) {

			$url = $destinationPath.$filename;

			$expriedDate = $input['expired_date'];
			$date = date_create($expriedDate);
			$dateEx = date_format($date, '20-12-31');
			//$date = new DateTime($expriedDate);
			$expriedDate = date_format($date, 'y-m-d');
			if($expriedDate < $dateEx) {
				$expriedDate = $dateEx;
			} else {
				//echo '234';die;
				//$expriedDate = date_format($date, '20-12-31');
			}

			
			$provider = $input['provider__id']; //Esales/vnpt Pay
			$operator = $input['operator__id']; //Viettel//mobilephone/vinaphone
			$price = $input['price__id'];

			$providerName = $providers[$provider];
			$operatorName = $operators[$operator];
			$priceName = $prices[$price];

			
			$realFilePath = $url;
			$millisecond     = round(microtime(true)*1000);

			
			$tempArray = array();
			if($providerName == 'Esales') {
				$list = self::importEsales($realFilePath);
				unlink($realFilePath);
				foreach($list as $key=> $listPrice) {
					$priceName = $key*1000;
					$newfile = $operatorName . '_' .$providerName . '_' .$priceName.'_'. $expriedDate .'_' . $millisecond; // 'Esales_VT_20_19-12-30';

					$newfiletxt = $newfile.'.txt';
					
					$realNewFilePath = $destinationPath.$newfiletxt;
					


					$fh = fopen($realNewFilePath, "w") or die("Unable to open file!");
					//code block password
					$password = self::randomPassword();
					$iv = substr(md5('1pay'.$password, true), 0, 8);
					$keys = substr(md5('trumoney'.$password, true) . 
					               md5('vn'.$password, true), 0, 24);

					$opts = array('iv'=>$iv, 'key'=>$keys);
					//end code block password
					stream_filter_append($fh, 'mcrypt.tripledes', STREAM_FILTER_WRITE, $opts);
					$time = time();
					fwrite($fh, $time);
					fwrite($fh, "\n");
					fwrite($fh, $priceName);
					fwrite($fh, "\n");
					fwrite($fh, $expriedDate);
					fwrite($fh, "\n");
					foreach($listPrice as $item) {
						
						$serial = trim($item['3']);
						$pin = trim($item['4']);

						fwrite($fh, $serial);
						fwrite($fh, " ");
						fwrite($fh, $pin);
						fwrite($fh, "\n");
					}

					$tempArray[$key]['filename'] = URL::to('/'). '/images/import_pin/' .$newfiletxt;

					
					$tempArray[$key]['password'] = $password;

					
				}
				$msg = array('type'=>'success','msg'=>'Upload success - Link -     ' . json_encode($tempArray));
					array_push($msgs,$msg);
					return Redirect::back()
			            ->withInput()
			            ->with('msgs', $msgs);
				// $result['password'] = $password;
				// $result['realNewFilePath'] = $realNewFilePath;
				// echo json_encode($result);die;
			} else if($providerName == 'VNPTEpay') {
				if($extension == 'txt') {
					$list = self::importVNPTEpay($realFilePath);
					unlink($realFilePath);
					foreach($list as $item) {
						$serial = trim($item['6']);
						$pin = trim($item['7']);

						fwrite($fh, $serial);
						fwrite($fh, " ");
						fwrite($fh, $pin);
						fwrite($fh, "\n");
					}
					// $result['password'] = $password;
					// $result['realNewFilePath'] = $realNewFilePath;
					// echo json_encode($result);die;
				} else if($extension == 'xls') {
					$list = self::importVNPTEpay($realFilePath);
					unlink($realFilePath);
					foreach($list as $key=>$item) {
						if($key >= 3) {
							$serial = trim($item['4']);
							$pin = trim($item['5']);

							fwrite($fh, $serial);
							fwrite($fh, " ");
							fwrite($fh, $pin);
							fwrite($fh, "\n");

							
						}
						
					}
					// $result['password'] = $password;
					// $result['realNewFilePath'] = $realNewFilePath;
					// echo json_encode($result);die;
					
				}
			} else if($providerName == 'VNPay') {
				if($extension == 'xls' || $extension == 'xlsx') {
					$list = self::importVNPay($realFilePath);
					unlink($realFilePath);
					foreach($list as $key=> $listPrice) {
						$priceName = $key*1000;
						$newfile = $operatorName . '_' .$providerName . '_' .$priceName.'_'. $expriedDate .'_' . $millisecond; // 'Esales_VT_20_19-12-30';

						$newfiletxt = $newfile.'.txt';
						
						$realNewFilePath = $destinationPath.$newfiletxt;
						
						$fh = fopen($realNewFilePath, "w") or die("Unable to open file!");
						//code block password
						$password = self::randomPassword();
						$iv = substr(md5('1pay'.$password, true), 0, 8);
						$keys = substr(md5('trumoney'.$password, true) . 
									   md5('vn'.$password, true), 0, 24);

						$opts = array('iv'=>$iv, 'key'=>$keys);
						//end code block password
						stream_filter_append($fh, 'mcrypt.tripledes', STREAM_FILTER_WRITE, $opts);
						$time = time();
						fwrite($fh, $time);
						fwrite($fh, "\n");
						fwrite($fh, $priceName);
						fwrite($fh, "\n");
						fwrite($fh, $expriedDate);
						fwrite($fh, "\n");
						foreach($listPrice as $item) {
							
							$serial = trim($item['serial']);
							$code = trim($item['code']);

							fwrite($fh, $serial);
							fwrite($fh, " ");
							fwrite($fh, $code);
							fwrite($fh, "\n");
						}

						$tempArray[$key]['filename'] = URL::to('/'). '/images/import_pin/' .$newfiletxt;

						
						$tempArray[$key]['password'] = $password;

						
					}
					// $result['password'] = $password;
					// $result['realNewFilePath'] = $realNewFilePath;
					// echo json_encode($result);die;
					
				}
				$msg = array('type'=>'success','msg'=>'Upload success - Link -     ' . json_encode($tempArray));
					array_push($msgs,$msg);
					return Redirect::back()
			            ->withInput()
			            ->with('msgs', $msgs);
				
			} else if($providerName == 'FormatFile.txt') {
				$password = self::randomPassword();
				$iv = substr(md5('1pay'.$password, true), 0, 8);
				$key = substr(md5('trumoney'.$password, true) . 
		      	md5('vn'.$password, true), 0, 24);

				$opts = array('iv'=>$iv, 'key'=>$key);
				$fp = fopen($realFilePath, 'rb');

				stream_filter_append($fp, 'mcrypt.tripledes', STREAM_FILTER_READ, $opts);
				$data = rtrim(stream_get_contents($fp));
				fclose($fp);
				$newfile = $operatorName . '_' .$providerName . '_' .$priceName.'_'. $expriedDate .'_' . $millisecond; // 'Esales_VT_20_19-12-30';

				$newfiletxt = $newfile.'.txt';
				$realNewFilePath = $destinationPath.$newfiletxt;
				File::put( $realNewFilePath, $data );
				$tempArray['filename'] = URL::to('/'). '/images/import_pin/' .$newfiletxt;

					
				$tempArray['password'] = $password;

					
			}
			$msg = array('type'=>'success','msg'=>'Upload success - Link -     ' . json_encode($tempArray));
				array_push($msgs,$msg);
				return Redirect::back()
		            ->withInput()
		            ->with('msgs', $msgs);
			
			

			echo $realNewFilePath.'--';
			echo $url;die;
		} else {
			echo 'Error!please try again!';die;
		}


		if(!empty($excelList) && $excelList->count()){
			foreach ($excelList as $key => $value) {
				var_dump($value);
			}
			dd($excelList);
		}
	}
	public function decodeFile($sourceFile) {

	}
	public function importEsales($sourceFile) {		
		require_once public_path(). '/laravel-app/app/libraries/simple_html_dom.php';
	
		//$destinationPath = public_path().'/images/import_pin/';
		//$file = 'ThongTinBanThe_20-08-2016.xls';


		//$url = $sourceFile;
		$url = $sourceFile;
		

		$html = file_get_html($url);
		$list = $html->find('table', 1);
		$result  = $list->find('tr');
		$i = 0;
		$listItem = array();
		$vowels = array(" ", "-", "_");
		
		foreach($result as $trItem) {

			//igore $trItem[0]
			if($i != 0) {
				$price = $trItem->find('td',2)->innertext;
				if($price == '10.000 vnd') {
					$priceName = '10';
					foreach($trItem->find('td') as $tdItem) {
						$listItem[$priceName][$i][] = str_replace($vowels, "", trim($tdItem->innertext));
					}


				} else if($price == '20.000 vnd') {
					$priceName = '20';
					foreach($trItem->find('td') as $tdItem) {
						$listItem[$priceName][$i][] = str_replace($vowels, "", trim($tdItem->innertext));
					}

				} else if($price == '50.000 vnd') {
					$priceName = '50';
					foreach($trItem->find('td') as $tdItem) {
						$listItem[$priceName][$i][] = str_replace($vowels, "", trim($tdItem->innertext));
					}
				} else if($price == '100.000 vnd') {
					$priceName = '100';
					foreach($trItem->find('td') as $tdItem) {
						$listItem[$priceName][$i][] = str_replace($vowels, "", trim($tdItem->innertext));
					}
				} else if($price == '200.000 vnd') {
					$priceName = '200';
					foreach($trItem->find('td') as $tdItem) {
						$listItem[$priceName][$i][] = str_replace($vowels, "", trim($tdItem->innertext));
					}
				} else if($price == '500.000 vnd') {
					$priceName = '500';
					foreach($trItem->find('td') as $tdItem) {
						$listItem[$priceName][$i][] = str_replace($vowels, "", trim($tdItem->innertext));
					}
				}else if($price == '300.000 vnd') {
					$priceName = '300';
					foreach($trItem->find('td') as $tdItem) {
						$listItem[$priceName][$i][] = str_replace($vowels, "", trim($tdItem->innertext));
					}
				}else if($price == '30.000 vnd') {
					$priceName = '30';
					foreach($trItem->find('td') as $tdItem) {
						$listItem[$priceName][$i][] = str_replace($vowels, "", trim($tdItem->innertext));
					}
				}
				
				//$list[] = $listItem;				
			}
			
			$i++;
		}
		
		return $listItem;
		//dd($html);
	}
	public function test() {
		/*
		$html = '';
		$alphabet = '1234567890';
	    $list = "<tr>
		<td style='text-align:center;background-color:#eaeaea;'>1</td>
		<td style='text-align:center;background-color:#eaeaea;'>Vinaphone</td>
		<td style='text-align:right;background-color:#eaeaea;'>300.000 vnd</td>
		<td style='text-align:center;background-color:#eaeaea;'>serial</td>
		<td style='text-align:center;background-color:#eaeaea;'>code</td>
		<td style='text-align:center;background-color:#eaeaea;'>31/12/2020 23:59:00</td>
	</tr>";
	    
		for($ii=1; $ii<=500; $ii++) {
			$pass = array(); //remember to declare $pass as an array
		    $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
		    for ($i = 0; $i < 14; $i++) {
		        $n = rand(0, $alphaLength);
		        $pass[] = $alphabet[$n];
		    }

		    $serial = implode($pass);
		    $pass = array();
		    for ($i = 0; $i < 14; $i++) {
		        $n = rand(0, $alphaLength);
		        $pass[] = $alphabet[$n];
		    }
		    $code = implode($pass);
		   
		    $html = $html . $list;
		    $html = str_replace("serial",$serial,$html);
		    $html = str_replace("code",$code,$html);
		    
		   
		}
		var_dump($html);die;
		*/
		

		$destinationPath = public_path().'/images/import_pin/';
		$source = $destinationPath .'Format-VNPAY-Offline-Fake1.xlsx';
		//$url = $destinationPath . $source;
		//dd($source);
		$url = $source;
		$list = self::importVNPay($url);
		
		$operatorName = '';
		$expriedDate = '20-12-31';
		$providerName = '';
		$millisecond = '';
		
		foreach($list as $key=> $listPrice) {
			$priceName = $key*1000;
			$newfile = $operatorName . '_' .$providerName . '_' .$priceName.'_'. $expriedDate .'_' . $millisecond; // 'Esales_VT_20_19-12-30';

			$newfiletxt = $newfile.'.txt';
			
			$realNewFilePath = $destinationPath.$newfiletxt;
			
			$fh = fopen($realNewFilePath, "w") or die("Unable to open file!");
			//code block password
			$password = self::randomPassword();
			$iv = substr(md5('1pay'.$password, true), 0, 8);
			$keys = substr(md5('trumoney'.$password, true) . 
						   md5('vn'.$password, true), 0, 24);

			$opts = array('iv'=>$iv, 'key'=>$keys);
			//end code block password
			//stream_filter_append($fh, 'mcrypt.tripledes', STREAM_FILTER_WRITE, $opts);
			$time = time();
			fwrite($fh, $time);
			fwrite($fh, "\n");
			fwrite($fh, $priceName);
			fwrite($fh, "\n");
			fwrite($fh, $expriedDate);
			fwrite($fh, "\n");
			foreach($listPrice as $item) {
				
				$serial = trim($item['serial']);
				$code = trim($item['code']);

				fwrite($fh, $serial);
				fwrite($fh, " ");
				fwrite($fh, $code);
				fwrite($fh, "\n");
			}

			$tempArray[$key]['filename'] = URL::to('/'). '/images/import_pin/' .$newfiletxt;

			
			$tempArray[$key]['password'] = $password;

			
		}
				
		var_dump($tempArray);die;
		//check file is .txt or .xls
		$ext = pathinfo($source, PATHINFO_EXTENSION);
		if($ext == 'txt') {
			$file_content = file($source);
			if(count($file_content) > 1) {
				foreach($file_content as $key=>$item) {
					if($key != 0) {
						$parts = preg_split('/\s+/', $item);
						if(count($parts) > 0) {
							$listItem[] = $parts;
						}
						
						
					}
				}
			}
			dd($listItem);
			//dd($file_content);
		} else if($ext == 'xls') {
			$list = array();
			$excelList = Excel::load($source, function ($reader) { })->toArray();
			if(count($excelList) > 0) {
				$list = $excelList[count($excelList) - 1];
				if(count($list) > 3) {
					return $list;
				} 
				
			}
			return $list;
			
		}
		
		$file_content = file($source);
		$data = self::importVNPTEpay($source);
		dd($data);
	}
	public function importVNPTEpay($sourceFile) {
		$ext = pathinfo($sourceFile, PATHINFO_EXTENSION);
		if($ext == 'txt') {
			$file_content = file($sourceFile);
			if(count($file_content) > 1) {
				foreach($file_content as $key=>$item) {
					if($key != 0) {
						$parts = preg_split('/\s+/', $item);
						if(count($parts) > 0) {
							$listItem[] = $parts;
						}
						
						
					}
				}
			}
			return $listItem;
			//dd($file_content);
		} else if($ext == 'xls') {
			$list = array();
			$excelList = Excel::load($sourceFile, function ($reader) { })->toArray();
			if(count($excelList) > 0) {
				$list = $excelList[count($excelList) - 1];
				if(count($list) > 3) {
					return $list;
				} 
				
			}
			return $list;
		}
		

		
		//dd($html);
	}
	public function importVNPay($sourceFile) {
		$ext = pathinfo($sourceFile, PATHINFO_EXTENSION);
		if($ext == 'txt') {
			$file_content = file($sourceFile);
			if(count($file_content) > 1) {
				foreach($file_content as $key=>$item) {
					if($key != 0) {
						$parts = preg_split('/\s+/', $item);
						if(count($parts) > 0) {
							$listItem[] = $parts;
						}
						
						
					}
				}
			}
			return $listItem;
			//dd($file_content);
		} else if($ext == 'xls' || $ext == 'xlsx') {
			$list = array();
			$excelList = Excel::load($sourceFile, function ($reader) { })->toArray();
			
			$lists = $excelList[0];
			$i=0;
			
			foreach ($lists as $list) {
				
				if(count($list) > 0) {
					$price = $list['menh_gia'];
					if($price == '10.000') {
						$priceName = '10';
						
					} else if($price == '20.000') {
						$priceName = '20';
						
					} else if($price == '50.000') {
						$priceName = '50';
						
					} else if($price == '100.000') {
						$priceName = '100';
						
					} else if($price == '200.000') {
						$priceName = '200';
						
					} else if($price == '500.000') {
						$priceName = '500';
						
					}else if($price == '300.000') {
						$priceName = '300';
						
					}else if($price == '30.000') {
						$priceName = '30';
						
					}
					$listItem[$priceName][$i]['price'] = $list['menh_gia'];
					$listItem[$priceName][$i]['serial'] = $list['so_serial'];
					$listItem[$priceName][$i]['code'] = $list['ma_nap_tien'];
				}
				$i++;
			}
			return $listItem;
		}
		

		
		//dd($html);
	}
	/**
	 * Display a listing of the resource.
	 * GET /pinstock
	 *
	 * @return Response
	 */
	public function encrypt() {

	}
	public function decrypt() {

	}
	public function index()
	{
		//
	}

	/**
	 * Show the form for creating a new resource.
	 * GET /pinstock/create
	 *
	 * @return Response
	 */
	public function create()
	{
		//
	}

	/**
	 * Store a newly created resource in storage.
	 * POST /pinstock
	 *
	 * @return Response
	 */
	public function store()
	{
		//
	}

	/**
	 * Display the specified resource.
	 * GET /pinstock/{id}
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		//
	}

	/**
	 * Show the form for editing the specified resource.
	 * GET /pinstock/{id}/edit
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 * PUT /pinstock/{id}
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 * DELETE /pinstock/{id}
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		//
	}

}
