<?php
   //header('Content-Type: text/html; charset=utf-8');
   error_reporting(E_ALL);
   ini_set('display_errors', '1');
   date_default_timezone_set('America/Sao_Paulo');

   include_once("../api/nusoap/nusoap.php");
   
   $myNamespace = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'];

   ini_set( 'soap.wsdl_cache_enabled', '0' );

   //$soap_config = array( 'encoding' => 'UTF-8' );

   $server = new soap_server();
   $server->configureWSDL('gravaPsgSiare', 'urn:' . $myNamespace);
   $server->soap_defencoding = 'UTF-8';
   $server->decode_utf8 = false;
   $server->encode_utf8 = true;
   
   $server->wsdl->schemaTargetNamespace = 'urn:' . $myNamespace;
   
   // gravaPsgSiare
   $server->register('gravaPsgSiare',            //nome do metodo
            array('pservico' => 'xsd:string',
                  'pmetodo'  => 'xsd:string',
                  'pcodemp'  => 'xsd:string',
                  'pidpas'   => 'xsd:string',
                  'pjsonPas' => 'xsd:string',
                  'pjsonRel' => 'xsd:string',
                  'pjsonSer' => 'xsd:string',
                  'pjsonPec' => 'xsd:string',
                  'pjsonCli' => 'xsd:string',
                  'pjsonVei' => 'xsd:string'
                  ),                              //parametros de entrada
            array('gravaPsgSiare'=>'xsd:string'), //parametros de saída
            
            $myNamespace,                         //namespace
            false,                                //soapaction
            'rpc',                                //style
            'literal',                            //use
            'Gravar Passagens Veiculos Siare'     //documentacao do servico
   );

   // BuscaUsuario
   $server->register('BuscaUsuario',             //nome do metodo
            array('pservico' => 'xsd:string',
                  'pmetodo'  => 'xsd:string',
                  'pemail'   => 'xsd:string'
                  ),                              //parametros de entrada
            array('BuscaUsuario'=>'xsd:string'),  //parametros de saída
            
            $myNamespace,                         //namespace
            false,                                //soapaction
            'rpc',                                //style
            'literal',                            //use
            'Busca dados do Usuario'              //documentacao do servico
   );

   ############################################################################
   function gravaPsgSiare($pservico, $pmetodo , $pcodemp , $pidpas  ,
                          $pjsonPas, $pjsonRel, $pjsonSer, $pjsonPec, 
                          $pjsonCli, $pjsonVei){
   ############################################################################
      /*
      http://siare08.procyon.com.br:3125/wss/srv/gravaPsgSiare.php
      http://siareweb.procyon.com.br:3125
      */
      
      $pjsonPas = str_replace('{"ProDataSet":','',$pjsonPas);
      $pjsonPas = str_replace('}]}}','}]}',$pjsonPas);

      $pjsonRel = str_replace('{"ProDataSet":','',$pjsonRel);
      $pjsonRel = str_replace('}]}}','}]}',$pjsonRel);

      $pjsonSer = str_replace('{"ProDataSet":','',$pjsonSer);
      $pjsonSer = str_replace('}]}}','}]}',$pjsonSer);

      $pjsonPec = str_replace('{"ProDataSet":','',$pjsonPec);
      $pjsonPec = str_replace('}]}}','}]}',$pjsonPec);

      $pjsonCli = str_replace('{"ProDataSet":','',$pjsonCli);
      $pjsonCli = str_replace('}]}}','}]}',$pjsonCli);

      $pjsonVei = str_replace('{"ProDataSet":','',$pjsonVei);
      $pjsonVei = str_replace('}]}}','}]}',$pjsonVei);

      $Servidor   = 'http://'.$_SERVER['HTTP_HOST'];
      $url        = $Servidor.'/cgi-bin/siarewebtt.pl/wficha';
      $Parametros = array('pservico' => $pservico,
                          'pmetodo'  => $pmetodo,
                          'pcodemp'  => $pcodemp,
                          'pidipas'  => $pidpas,
                          'pjsonPas' => $pjsonPas,
                          'pjsonRel' => $pjsonRel,
                          'pjsonSer' => $pjsonSer,
                          'pjsonPec' => $pjsonPec,
                          'pjsonCli' => $pjsonCli,
                          'pjsonVei' => $pjsonVei
                         );

      $url_str = $url
               . '?pservico=' . $pservico
               . '&pmetodo='  . $pmetodo
               . '&pcodemp='  . $pcodemp
               . '&pidipas='  . $pidpas
               . '&pjsonPas=' . $pjsonPas
               . '&pjsonRel=' . $pjsonRel
               . '&pjsonSer=' . $pjsonSer
               . '&pjsonPec=' . $pjsonPec
               . '&pjsonCli=' . $pjsonCli
               . '&pjsonVei=' . $pjsonVei;
      
      $strConf = print_r($Parametros, true);
      $Salva = fopen('/siare/baseweb/us/procyon/gravaPsgSiare.txt', 'w');
      fwrite($Salva, $url_str."\n\n");
      fwrite($Salva, $strConf);
      fclose($Salva);
      
      $ch = curl_init();

      curl_setopt_array($ch, array(
          CURLOPT_RETURNTRANSFER => 1,
          CURLOPT_URL            => $url,
          CURLOPT_ENCODING       => "gzip, deflate",
          CURLOPT_POST           => 1,
          CURLOPT_POSTFIELDS     => $Parametros
      ));
      
      $response = curl_exec($ch);
      
      if ($response === false) {
         $result = 'Erro Curl: ' . curl_error($ch);
         curl_close($ch);
      } else {
         curl_close($ch);
         
         if ($response == "") {
            $result = '<retorno>Ocorreu um erro na comunicação com o servidor!</retorno>';
         } else {
            $json = json_decode($response);
            $arr  = objectToArray($json);

            $xml_data = new SimpleXMLElement('<GravarResult/>');
            array_to_xml($arr, $xml_data);
            $result = str_replace('<?xml version="1.0"?>','',$xml_data->asXML());
            $result = str_replace('<GravarResult>','',$result);
            $result = str_replace('</GravarResult>','',$result);
            $result = str_replace('<item0>','',$result);
            $result = str_replace('</item0>','',$result);
         }
      }
      
      ##################### Debug #####################
      // rewind($verbose);
      // $verboseLog = stream_get_contents($verbose);
      // echo "Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";         
      #################################################
      //curl_close($ch);
      
      return $result;
   }
   
   ############################################################################
   function BuscaUsuario($pservico, $pmetodo , $pemail){
   ############################################################################
      /*
      http://siare08.procyon.com.br:3125/wss/srv/gravaPsgSiare.php
      http://siareweb.procyon.com.br:3125
      */
      $Servidor   = 'http://'.$_SERVER['HTTP_HOST'];
      $url        = $Servidor.'/cgi-bin/siarewebtt.pl/wfcusu';
      $Parametros = array('pservico' => $pservico,
                          'pmetodo'  => $pmetodo,
                          'pemail'   => $pemail
                         );

      $url_str = $url
               . '?pservico=' . $pservico
               . '&pmetodo='  . $pmetodo
               . '&pemail='   . $pemail;
      
      $strConf = print_r($Parametros, true);
      $Salva = fopen('/siare/baseweb/us/procyon/gravaPsgSiare.txt', 'w');
      fwrite($Salva, $url_str."\n\n");
      fwrite($Salva, $strConf);
      fclose($Salva);
      
      $ch = curl_init();

      curl_setopt_array($ch, array(
          CURLOPT_RETURNTRANSFER => 1,
          CURLOPT_URL            => $url,
          CURLOPT_ENCODING       => "gzip, deflate",
          CURLOPT_POST           => 1,
          CURLOPT_POSTFIELDS     => $Parametros
      ));
      
      $response = curl_exec($ch);
      
      if ($response === false) {
         $result = 'Erro Curl: ' . curl_error($ch);
         curl_close($ch);
      } else {
         curl_close($ch);
         
         if ($response == "") {
            $result = '<retorno>Ocorreu um erro na comunicação com o servidor!</retorno>';
         } else {
            $json = json_decode($response);
            $arr  = objectToArray($json);

            $xml_data = new SimpleXMLElement('<GravarResult/>');
            array_to_xml($arr, $xml_data);
            $result = str_replace('<?xml version="1.0"?>','',$xml_data->asXML());
            $result = str_replace('<GravarResult>','',$result);
            $result = str_replace('</GravarResult>','',$result);
            $result = str_replace('<item0>','',$result);
            $result = str_replace('</item0>','',$result);
         }
      }
      
      ##################### Debug #####################
      // rewind($verbose);
      // $verboseLog = stream_get_contents($verbose);
      // echo "Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";         
      #################################################
      //curl_close($ch);
      
      return $result;
   }
   
   ############################################################################
   function gzdecode($data){
   ############################################################################
     $g=tempnam('/tmp','ff');
     @file_put_contents($g,$data);
     ob_start();
     readgzfile($g);
     $d=ob_get_clean();
     return $d;
   }

   ############################################################################
   function objectToArray($d) {
   ############################################################################
      if (is_object($d)) {
         $d = get_object_vars($d);
      }

      if (is_array($d)) {
         return array_map(__FUNCTION__, $d);
      }
      else {
         return $d;
      }
   }
   
   ############################################################################
   function array_to_xml( $data, &$xml_data ) {
   ############################################################################
      foreach( $data as $key => $value ) {
         if( is_array($value) ) {
            if( is_numeric($key) ){
                $key = 'item'.$key; //dealing with <0/>..<n/> issues
            }
            
            if (is_array($value)) {
               $subnode = $xml_data->addChild($key);
               array_to_xml($value, $subnode);
            }
         } else {
            $xml_data->addChild("$key",htmlspecialchars("$value"));
         }
      }
   }
   
   $HTTP_RAW_POST_DATA = isset($HTTP_RAW_POST_DATA) ? $HTTP_RAW_POST_DATA : '';
   $server->service($HTTP_RAW_POST_DATA);
   exit();

?>