<?php
error_reporting(E_ERROR);
ini_set('display_errors', 'on');
ini_set('default_charset', 'UTF-8');
date_default_timezone_set("America/Sao_Paulo");
set_time_limit(0);

################################# Parametros #################################
## Entrada dos Parametros
function param($par = array()) {
   $campo = '';
   $valor = '';
   $arr   = array();
   for ($i=0;$i < count($par);$i++) {
      if (substr($par[$i],0,1) == '-') {
         $campo = substr($par[$i],1,1);
      } else {
         $valor = substr($par[$i],0);
         if (!empty($campo)) { $arr[$campo] = $valor; }
      }
   }
   return $arr;
}

$param = array();
if (PHP_SAPI != 'cli') {
   if ($_SERVER['REQUEST_METHOD'] == 'POST') {
      $conteudo = $_POST;
   } elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
      $conteudo = $_GET;
   } else {
      header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
      die('{"msg": "Metodo nao encontrado."}');
   }

   $prefix = (((!empty($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS']!=='off') ||
            $_SERVER['SERVER_PORT']==443) ? 'https://' : 'http://' );

   $dirweb  = $prefix.$_SERVER['HTTP_HOST'] . '/wss/';
} else {
   $conteudo = param($GLOBALS['argv']);
   $dirweb   = '';
}

$param   = $conteudo;

## Atribuicao dos Parametros
$Empresa = (isset($param['e']) ? $param['e'] : '');

if (empty($Empresa)) {
   $Empresa = 'gravaFicha';
}

$dirloc  = realpath(dirname(__FILE__)) . '/';
//echo date("Ymd") . ' - ' . date_default_timezone_get() . '<br/>';
$log_file = $Empresa.'_'.date("Ymd").'.log';
define('LOG_FILE',  $log_file);

$arqjson = (PHP_SAPI != 'cli' ? '' : $dirloc).$Empresa.'.json';

$log_path = $dirloc . 'logs/';
define('LOG_PATH',  $log_path);

define('TXT_FILE',  $Empresa.'.txt');
define('TXT_PATH',  $dirloc.'txt/');

if (!file_exists($arqjson)) {
   salvaLog(array("ERRO: O arquivo '" . $arqjson ."' "
      . "nao foi encontrado no diretorio da aplicacao."));
   exit(1);
}

## Carrega Parametros do arquivo Json
$arqpar = file_get_contents($arqjson);

$enclist = 'UTF-8,ASCII,ISO-8859-1,ISO-8859-2,ISO-8859-3,ISO-8859-4,ISO-8859-5'
         . 'ISO-8859-6,ISO-8859-7,ISO-8859-8,ISO-8859-9,ISO-8859-10'
         . 'ISO-8859-13,ISO-8859-14,ISO-8859-15,ISO-8859-16'
         . 'Windows-1251,Windows-1252,Windows-1254';
define('ENCLIST',  $enclist);

$encoding = mb_detect_encoding($arqpar,ENCLIST);
if($encoding != 'UTF-8') {
   $arqpar = mb_convert_encoding($arqpar, 'UTF-8', $encoding);
   $par    = json_decode($arqpar);
} else {
   $par = json_decode(utf8_encode($arqpar));
}

if (json_last_error() !== JSON_ERROR_NONE) {
   salvaLog(array("ERRO: '" . json_validate(utf8_encode(file_get_contents($arqjson))) ."'"));
   exit(1);
}

$p = $par->Parametros;

## Parametros ##

## Empresa
$odbc_name  = utf8_decode($p->ODBC_Nome);
$pcodemp    = utf8_decode($p->Login);

## Registos
$regs       = $p->RegPorVez; // Numero de Registros enviados por ciclo
$rba        = $p->RegBuscAbertos; // Numeros de Registros retroativos ao ultimo gravado
$implanta   = $p->Implantacao; // Ignora a ultima passagem
$saida      = $p->Saida; // Texto ou WebService

$time_zone  = $p->TimeZone; // http://php.net/date.timezone
$Tipo_DB    = strtoupper($p->TipoDB); // Utilizado para parametros do SQL
define('TIPO_DB',  $Tipo_DB);

date_default_timezone_set($time_zone);
$dataLimite = (date('Y') - $p->Periodo); // Anos retroativos ao Atual para inicio da busca

$tempo      = $p->Tempo; // Tempo de cada Ciclo
$tempo      = ($tempo * 3600); // 60s = 1m  / 3600s = 1h

$TipoConexao = $p->TipoConexao; // Tipo de Conexao com o Banco de Dados
$PDO         = $p->PDO;         // Server, Port, Data, User, Password

## Arquivo de Logs
$salvaLogs  = $p->SalvaLogs; // Salva no arquivo de log os dados do envio de cada Registro
$log_path   = utf8_decode($p->CaminhoLogs); // Pasta para armazenamentos dos arquivos de Log
define('SALVA_LOGS',  $salvaLogs);
define('LOG_PATH',  $log_path);


## Valida se PDO esta habilitado no php.ini
if ($TipoConexao == 'PDO') {
   $PDO_Ok = NULL;
   foreach(PDO::getAvailableDrivers() as $driver) {
      if (strtolower(TIPO_DB)==strtolower($driver)) {
         $PDO_Ok = $driver;
      }
   }
   
   if ($PDO_Ok == NULL) {
      salvaLog(array("Extesão PDO para o DB " . strtoupper(TIPO_DB) . " deve estar habilitada no 'php.ini'"));
      exit;
   }
}

## Servico de sincronizacao (Webservice da Ficha do Carro)
$Servidor = $p->Servidor;
$Porta    = $p->Porta;
$Servico  = $p->Servicow;

$url = $Servidor;
if (!empty($Porta)) {
   $url .= ':'.$Porta;
}
$url .= $Servico;

## Proxy
$usaProxy = $p->Proxy->UsaProxy; // Utiliza Proxy ("true"/"false")
$proxyPar = array("Servidor" => $p->Proxy->ServidorP,
                  "Porta"    => $p->Proxy->PortaP,
                  "Usuario"  => utf8_decode($p->Proxy->Usuario),
                  "Senha"    => utf8_decode($p->Proxy->Senha)
                  );

define('PROXY',    $usaProxy);
define('USUARIO',  $proxyPar['Usuario']);
define('SENHA',    $proxyPar['Senha']);
define('SERVIDOR', $proxyPar['Servidor']);
define('PORTA',    $proxyPar['Porta']);

### Parametros das Tabelas para montagem do SQL

## Passagens
# Formato do Conteudo do Campo da OS/Passagem
$ForPas = (property_exists($p->Passagens, 'FormatoPas') ? $p->Passagens->FormatoPas : '');

if (property_exists($p, 'Passagens')) {
   # Valida Conexao
   if ($TipoConexao == 'PDO') {
      if (property_exists($p->Passagens, 'PDO')) {
         $os_pdo_ord = $p->Passagens->PDO; // Server, Port, Data, User, Password
      }
   } elseif (property_exists($p->Passagens, 'ODBC')) {
      if (utf8_decode($p->Passagens->ODBC) !== $odbc_name) {
         $os_odb_ord = (utf8_decode($p->Passagens->ODBC));
      } else {
         $os_odb_ord = $odbc_name;
      }
   } else {
      $os_odb_ord = $odbc_name;
   }

   # Busca Tabela (From)
   $os_tab_ord = utf8_decode($p->Passagens->Tabela);

   # Busca Campos
   $campos_ord = $p->Passagens->Campos;
   foreach($campos_ord as $key => $value) {
      $os_cam_ord[utf8_decode($key)] = utf8_decode($value);
   }

   # Busca Filtros (Where)
   $cond = $p->Passagens->Condicao;
   $os_con_ord = array();
   for ($i = 0;$i < count($cond); $i++) {
      $os_con_ord[] = objectToArray($cond[$i]);
   }

   # Busca Ordem dos Campos (Order By)
   $os_ord_ord = $p->Passagens->Ordem;

} else {
   $os_pdo_ord = NULL;
   $os_odb_ord = NULL;
   $os_tab_ord = NULL;
   $os_cam_ord = NULL;
   $os_con_ord = NULL;
   $os_ord_ord = NULL;
}

## Relatos
if (property_exists($p, 'Relatos')) {
   # Valida Conexao
   if ($TipoConexao == 'PDO') {
      if (property_exists($p->Relatos, 'PDO')) {
         $os_pdo_rel = $p->Relatos->PDO; // Server, Port, Data, User, Password
      }
   } elseif (property_exists($p->Relatos, 'ODBC')) {
      if (utf8_decode($p->Relatos->ODBC) !== $odbc_name) {
         $os_odb_rel = (utf8_decode($p->Relatos->ODBC));
      } else {
         $os_odb_rel = $odbc_name;
      }
   } else {
      $os_odb_rel = $odbc_name;
   }

   # Busca Tabela (From)
   $os_tab_rel = utf8_decode($p->Relatos->Tabela);

   # Busca Campos
   $campos_rel = $p->Relatos->Campos;
   foreach($campos_rel as $key => $value) {
      $os_cam_rel[utf8_decode($key)] = utf8_decode($value);
   }

   # Busca Filtros (Where)
   $cond = $p->Relatos->Condicao;
   $os_con_rel = array();
   for ($i = 0;$i < count($cond); $i++) {
      $os_con_rel[] = objectToArray($cond[$i]);
   }

   # Busca Ordem dos Campos (Order By)
   $os_rel_rel = $p->Relatos->Ordem;

} else {
   $os_pdo_rel = NULL;
   $os_odb_rel = NULL;
   $os_tab_rel = NULL;
   $os_cam_rel = NULL;
   $os_con_rel = NULL;
   $os_ord_rel = NULL;
}

## Servicos
if (property_exists($p, 'Servicos')) {
   # Valida Conexao
   if ($TipoConexao == 'PDO') {
      if (property_exists($p->Servicos, 'PDO')) {
         $os_pdo_ser = $p->Servicos->PDO; // Server, Port, Data, User, Password
      }
   } elseif (property_exists($p->Servicos, 'ODBC')) {
      if (utf8_decode($p->Servicos->ODBC) !== $odbc_name) {
         $os_odb_ser = (utf8_decode($p->Servicos->ODBC));
      } else {
         $os_odb_ser = $odbc_name;
      }
   } else {
      $os_odb_ser = $odbc_name;
   }

   # Busca Tabela (From)
   $os_tab_ser = utf8_decode($p->Servicos->Tabela);

   # Busca Campos
   $campos_ser = $p->Servicos->Campos;
   foreach($campos_ser as $key => $value) {
      $os_cam_ser[utf8_decode($key)] = utf8_decode($value);
   }

   # Busca Filtros (Where)
   $cond = $p->Servicos->Condicao;
   $os_con_ser = array();
   for ($i = 0;$i < count($cond); $i++) {
      $os_con_ser[] = objectToArray($cond[$i]);
   }

   # Busca Ordem dos Campos (Order By)
   $os_ord_ser = $p->Servicos->Ordem;

} else {
   $os_pdo_ser = NULL;
   $os_odb_ser = NULL;
   $os_tab_ser = NULL;
   $os_cam_ser = NULL;
   $os_con_ser = NULL;
   $os_ord_ser = NULL;
}

## Terceiros
if (property_exists($p, 'Terceiros')) {
   # Valida Conexao
   if ($TipoConexao == 'PDO') {
      if (property_exists($p->Terceiros, 'PDO')) {
         $os_pdo_ter = $p->Terceiros->PDO; // Server, Port, Data, User, Password
      }
   } elseif (property_exists($p->Terceiros, 'ODBC')) {
      if (utf8_decode($p->Terceiros->ODBC) !== $odbc_name) {
         $os_odb_ter = (utf8_decode($p->Terceiros->ODBC));
      } else {
         $os_odb_ter = $odbc_name;
      }
   } else {
      $os_odb_ter = $odbc_name;
   }

   # Busca Tabela (From)
   $os_tab_ter = utf8_decode($p->Terceiros->Tabela);

   # Busca Campos
   $campos_ter = $p->Terceiros->Campos;
   foreach($campos_ter as $key => $value) {
      $os_cam_ter[utf8_decode($key)] = utf8_decode($value);
   }

   # Busca Filtros (Where)
   $cond = $p->Terceiros->Condicao;
   $os_con_ter = array();
   for ($i = 0;$i < count($cond); $i++) {
      $os_con_ter[] = objectToArray($cond[$i]);
   }

   # Busca Ordem dos Campos (Order By)
   $os_ord_ter = $p->Terceiros->Ordem;

} else {
   $os_pdo_ter = NULL;
   $os_odb_ter = NULL;
   $os_tab_ter = NULL;
   $os_cam_ter = NULL;
   $os_con_ter = NULL;
   $os_ord_ter = NULL;
}

## Servico
if (property_exists($p, 'Servico')) {
   # Valida Conexao
   if ($TipoConexao == 'PDO') {
      if (property_exists($p->Servico, 'PDO')) {
         $os_pdo_sev = $p->Servico->PDO; // Server, Port, Data, User, Password
      }
   } elseif (property_exists($p->Servico, 'ODBC')) {
      if (utf8_decode($p->Servico->ODBC) !== $odbc_name) {
         $os_odb_sev = (utf8_decode($p->Servico->ODBC));
      } else {
         $os_odb_sev = $odbc_name;
      }
   } else {
      $os_odb_sev = $odbc_name;
   }

   # Busca Tabela (From)
   $os_tab_sev = utf8_decode($p->Servico->Tabela);

   # Busca Campos
   $campos_sev = $p->Servico->Campos;
   foreach($campos_sev as $key => $value) {
      $os_cam_sev[utf8_decode($key)] = utf8_decode($value);
   }

   # Busca Filtros (Where)
   $cond = $p->Servico->Condicao;
   $os_con_sev = array();
   for ($i = 0;$i < count($cond); $i++) {
      $os_con_sev[] = objectToArray($cond[$i]);
   }

   # Busca Ordem dos Campos (Order By)
   $os_ord_sev = $p->Servico->Ordem;

} else {
   $os_pdo_sev = NULL;
   $os_odb_sev = NULL;
   $os_tab_sev = NULL;
   $os_cam_sev = NULL;
   $os_con_sev = NULL;
   $os_ord_sev = NULL;
}

## Pecas
if (property_exists($p, 'Pecas')) {
   # Valida Conexao
   if ($TipoConexao == 'PDO') {
      if (property_exists($p->Pecas, 'PDO')) {
         $os_pdo_pec = $p->Pecas->PDO; // Server, Port, Data, User, Password
      }
   } elseif (property_exists($p->Pecas, 'ODBC')) {
      if (utf8_decode($p->Pecas->ODBC) !== $odbc_name) {
         $os_odb_pec = (utf8_decode($p->Pecas->ODBC));
      } else {
         $os_odb_pec = $odbc_name;
      }
   } else {
      $os_odb_pec = $odbc_name;
   }

   # Busca Tabela (From)
   $os_tab_pec = utf8_decode($p->Pecas->Tabela);

   # Busca Campos
   $campos_pec = $p->Pecas->Campos;
   foreach($campos_pec as $key => $value) {
      $os_cam_pec[utf8_decode($key)] = utf8_decode($value);
   }

   # Busca Filtros (Where)
   $cond = $p->Pecas->Condicao;
   $os_con_pec = array();
   for ($i = 0;$i < count($cond); $i++) {
      $os_con_pec[] = objectToArray($cond[$i]);
   }

   # Busca Ordem dos Campos (Order By)
   $os_ord_pec = $p->Pecas->Ordem;

} else {
   $os_pdo_pec = NULL;
   $os_odb_pec = NULL;
   $os_tab_pec = NULL;
   $os_cam_pec = NULL;
   $os_con_pec = NULL;
   $os_ord_pec = NULL;
}

## Produtos
if (property_exists($p, 'Produtos')) {
   # Valida Conexao
   if ($TipoConexao == 'PDO') {
      if (property_exists($p->Produtos, 'PDO')) {
         $os_pdo_pro = $p->Produtos->PDO; // Server, Port, Data, User, Password
      }
   } elseif (property_exists($p->Produtos, 'ODBC')) {
      if (utf8_decode($p->Produtos->ODBC) !== $odbc_name) {
         $os_odb_pro = (utf8_decode($p->Produtos->ODBC));
      } else {
         $os_odb_pro = $odbc_name;
      }
   } else {
      $os_odb_pro = $odbc_name;
   }

   # Busca Tabela (From)
   $os_tab_pro = utf8_decode($p->Produtos->Tabela);

   # Busca Campos
   $campos_pro = $p->Produtos->Campos;
   foreach($campos_pro as $key => $value) {
      $os_cam_pro[utf8_decode($key)] = utf8_decode($value);
   }

   # Busca Filtros (Where)
   $cond = $p->Produtos->Condicao;
   $os_con_pro = array();
   for ($i = 0;$i < count($cond); $i++) {
      $os_con_pro[] = objectToArray($cond[$i]);
   }

   # Busca Ordem dos Campos (Order By)
   $os_ord_pro = $p->Produtos->Ordem;

} else {
   $os_pdo_pro = NULL;
   $os_odb_pro = NULL;
   $os_tab_pro = NULL;
   $os_cam_pro = NULL;
   $os_con_pro = NULL;
   $os_ord_pro = NULL;
}

## Veiculos
if (property_exists($p, 'Veiculos')) {
   # Valida Conexao
   if ($TipoConexao == 'PDO') {
      if (property_exists($p->Veiculos, 'PDO')) {
         $os_pdo_vei = $p->Veiculos->PDO; // Server, Port, Data, User, Password
      }
   } elseif (property_exists($p->Veiculos, 'ODBC')) {
      if (utf8_decode($p->Veiculos->ODBC) !== $odbc_name) {
         $os_odb_vei = (utf8_decode($p->Veiculos->ODBC));
      } else {
         $os_odb_vei = $odbc_name;
      }
   } else {
      $os_odb_vei = $odbc_name;
   }

   # Busca Tabela (From)
   $os_tab_vei = utf8_decode($p->Veiculos->Tabela);

   # Busca Campos
   $campos_vei = $p->Veiculos->Campos;
   foreach($campos_vei as $key => $value) {
      $os_cam_vei[utf8_decode($key)] = utf8_decode($value);
   }

   # Busca Filtros (Where)
   $cond = $p->Veiculos->Condicao;
   $os_con_vei = array();
   for ($i = 0;$i < count($cond); $i++) {
      $os_con_vei[] = objectToArray($cond[$i]);
   }

   # Busca Ordem dos Campos (Order By)
   $os_ord_vei = $p->Veiculos->Ordem;

} else {
   $os_pdo_vei = NULL;
   $os_odb_vei = NULL;
   $os_tab_vei = NULL;
   $os_cam_vei = NULL;
   $os_con_vei = NULL;
   $os_ord_vei = NULL;
}

## Cor
if (property_exists($p, 'Cor')) {
   # Valida Conexao
   if ($TipoConexao == 'PDO') {
      if (property_exists($p->Cor, 'PDO')) {
         $os_pdo_cor = $p->Cor->PDO; // Server, Port, Data, User, Password
      }
   } elseif (property_exists($p->Cor, 'ODBC')) {
      if (utf8_decode($p->Cor->ODBC) !== $odbc_name) {
         $os_odb_cor = (utf8_decode($p->Cor->ODBC));
      } else {
         $os_odb_cor = $odbc_name;
      }
   } else {
      $os_odb_cor = $odbc_name;
   }

   # Busca Tabela (From)
   $os_tab_cor = utf8_decode($p->Cor->Tabela);

   # Busca Campos
   $campos_cor = $p->Cor->Campos;
   foreach($campos_cor as $key => $value) {
      $os_cam_cor[utf8_decode($key)] = utf8_decode($value);
   }

   # Busca Filtros (Where)
   $cond = $p->Cor->Condicao;
   $os_con_cor = array();
   for ($i = 0;$i < count($cond); $i++) {
      $os_con_cor[] = objectToArray($cond[$i]);
   }

   # Busca Ordem dos Campos (Order By)
   $os_ord_cor = $p->Cor->Ordem;

} else {
   $os_pdo_cor = NULL;
   $os_odb_cor = NULL;
   $os_tab_cor = NULL;
   $os_cam_cor = NULL;
   $os_con_cor = NULL;
   $os_ord_cor = NULL;
}

## Combustivel
if (property_exists($p, 'Combustivel')) {
   # Valida Conexao
   if ($TipoConexao == 'PDO') {
      if (property_exists($p->Combustivel, 'PDO')) {
         $os_pdo_cmb = $p->Combustivel->PDO; // Server, Port, Data, User, Password
      }
   } elseif (property_exists($p->Combustivel, 'ODBC')) {
      if (utf8_decode($p->Combustivel->ODBC) !== $odbc_name) {
         $os_odb_cmb = (utf8_decode($p->Combustivel->ODBC));
      } else {
         $os_odb_cmb = $odbc_name;
      }
   } else {
      $os_odb_cmb = $odbc_name;
   }

   # Busca Tabela (From)
   $os_tab_cmb = utf8_decode($p->Combustivel->Tabela);

   # Busca Campos
   $campos_cmb = $p->Combustivel->Campos;
   foreach($campos_cmb as $key => $value) {
      $os_cam_cmb[utf8_decode($key)] = utf8_decode($value);
   }

   # Busca Filtros (Where)
   $cond = $p->Combustivel->Condicao;
   $os_con_cmb = array();
   for ($i = 0;$i < count($cond); $i++) {
      $os_con_cmb[] = objectToArray($cond[$i]);
   }

   # Busca Ordem dos Campos (Order By)
   $os_ord_cmb = $p->Combustivel->Ordem;

} else {
   $os_pdo_cmb = NULL;
   $os_odb_cmb = NULL;
   $os_tab_cmb = NULL;
   $os_cam_cmb = NULL;
   $os_con_cmb = NULL;
   $os_ord_cmb = NULL;
}

## Marca
if (property_exists($p, 'Marca')) {
   # Valida Conexao
   if ($TipoConexao == 'PDO') {
      if (property_exists($p->Marca, 'PDO')) {
         $os_pdo_mar = $p->Marca->PDO; // Server, Port, Data, User, Password
      }
   } elseif (property_exists($p->Marca, 'ODBC')) {
      if (utf8_decode($p->Marca->ODBC) !== $odbc_name) {
         $os_odb_mar = (utf8_decode($p->Marca->ODBC));
      } else {
         $os_odb_mar = $odbc_name;
      }
   } else {
      $os_odb_mar = $odbc_name;
   }

   # Busca Tabela (From)
   $os_tab_mar = utf8_decode($p->Marca->Tabela);

   # Busca Campos
   $campos_mar = $p->Marca->Campos;
   foreach($campos_mar as $key => $value) {
      $os_cam_mar[utf8_decode($key)] = utf8_decode($value);
   }

   # Busca Filtros (Where)
   $cond = $p->Marca->Condicao;
   $os_con_mar = array();
   for ($i = 0;$i < count($cond); $i++) {
      $os_con_mar[] = objectToArray($cond[$i]);
   }

   # Busca Ordem dos Campos (Order By)
   $os_ord_mar = $p->Marca->Ordem;

} else {
   $os_pdo_mar = NULL;
   $os_odb_mar = NULL;
   $os_tab_mar = NULL;
   $os_cam_mar = NULL;
   $os_con_mar = NULL;
   $os_ord_mar = NULL;
}

## Modelo
if (property_exists($p, 'Modelo')) {
   # Valida Conexao
   if ($TipoConexao == 'PDO') {
      if (property_exists($p->Modelo, 'PDO')) {
         $os_pdo_mod = $p->Modelo->PDO; // Server, Port, Data, User, Password
      }
   } elseif (property_exists($p->Modelo, 'ODBC')) {
      if (utf8_decode($p->Modelo->ODBC) !== $odbc_name) {
         $os_odb_mod = (utf8_decode($p->Modelo->ODBC));
      } else {
         $os_odb_mod = $odbc_name;
      }
   } else {
      $os_odb_mod = $odbc_name;
   }

   # Busca Tabela (From)
   $os_tab_mod = utf8_decode($p->Modelo->Tabela);

   # Busca Campos
   $campos_mod = $p->Modelo->Campos;
   foreach($campos_mod as $key => $value) {
      $os_cam_mod[utf8_decode($key)] = utf8_decode($value);
   }

   # Busca Filtros (Where)
   $cond = $p->Modelo->Condicao;
   $os_con_mod = array();
   for ($i = 0;$i < count($cond); $i++) {
      $os_con_mod[] = objectToArray($cond[$i]);
   }

   # Busca Ordem dos Campos (Order By)
   $os_ord_mod = $p->Modelo->Ordem;

} else {
   $os_pdo_mod = NULL;
   $os_odb_mod = NULL;
   $os_tab_mod = NULL;
   $os_cam_mod = NULL;
   $os_con_mod = NULL;
   $os_ord_mod = NULL;
}

## Cliente
if (property_exists($p, 'Clientes')) {
   # Valida Conexao
   if ($TipoConexao == 'PDO') {
      if (property_exists($p->Clientes, 'PDO')) {
         $os_pdo_cli = $p->Clientes->PDO; // Server, Port, Data, User, Password
      }
   } elseif (property_exists($p->Clientes, 'ODBC')) {
      if (utf8_decode($p->Clientes->ODBC) !== $odbc_name) {
         $os_odb_cli = (utf8_decode($p->Clientes->ODBC));
      } else {
         $os_odb_cli = $odbc_name;
      }
   } else {
      $os_odb_cli = $odbc_name;
   }

   # Busca Tabela (From)
   $os_tab_cli = utf8_decode($p->Clientes->Tabela);

   # Busca Campos
   $campos_cli = $p->Clientes->Campos;
   foreach($campos_cli as $key => $value) {
      $os_cam_cli[utf8_decode($key)] = utf8_decode($value);
   }

   # Busca Filtros (Where)
   $cond = $p->Clientes->Condicao;
   $os_con_cli = array();
   for ($i = 0;$i < count($cond); $i++) {
      $os_con_cli[] = objectToArray($cond[$i]);
   }

   # Busca Ordem dos Campos (Order By)
   $os_ord_cli = $p->Clientes->Ordem;

} else {
   $os_pdo_cli = NULL;
   $os_odb_cli = NULL;
   $os_tab_cli = NULL;
   $os_cam_cli = NULL;
   $os_con_cli = NULL;
   $os_ord_cli = NULL;
}

## Email
if (property_exists($p, 'Email')) {
   # Valida Conexao
   if ($TipoConexao == 'PDO') {
      if (property_exists($p->Email, 'PDO')) {
         $os_pdo_ema = $p->Email->PDO; // Server, Port, Data, User, Password
      }
   } elseif (property_exists($p->Email, 'ODBC')) {
      if (utf8_decode($p->Email->ODBC) !== $odbc_name) {
         $os_odb_ema = (utf8_decode($p->Email->ODBC));
      } else {
         $os_odb_ema = $odbc_name;
      }
   } else {
      $os_odb_ema = $odbc_name;
   }

   # Busca Tabela (From)
   $os_tab_ema = utf8_decode($p->Email->Tabela);

   # Busca Campos
   $campos_ema = $p->Email->Campos;
   foreach($campos_ema as $key => $value) {
      $os_cam_ema[utf8_decode($key)] = utf8_decode($value);
   }

   # Busca Filtros (Where)
   $cond = $p->Email->Condicao;
   $os_con_ema = array();
   for ($i = 0;$i < count($cond); $i++) {
      $os_con_ema[] = objectToArray($cond[$i]);
   }

   # Busca Ordem dos Campos (Order By)
   $os_ord_ema = $p->Email->Ordem;

} else {
   $os_pdo_ema = NULL;
   $os_odb_ema = NULL;
   $os_tab_ema = NULL;
   $os_cam_ema = NULL;
   $os_con_ema = NULL;
   $os_ord_ema = NULL;
}


$dado = array();

if ($os_cam_ord != NULL) $dado[]['Passagens']   = $os_cam_ord;
if ($os_cam_rel != NULL) $dado[]['Relatos']     = $os_cam_rel;
if ($os_cam_ser != NULL) $dado[]['Servicos']    = $os_cam_ser;
if ($os_cam_ter != NULL) $dado[]['Terceiros']   = $os_cam_ter;
if ($os_cam_sev != NULL) $dado[]['Servico']     = $os_cam_sev;
if ($os_cam_pec != NULL) $dado[]['Pecas']       = $os_cam_pec;
if ($os_cam_pro != NULL) $dado[]['Produtos']    = $os_cam_pro;
if ($os_cam_vei != NULL) $dado[]['Veiculos']    = $os_cam_vei;
if ($os_cam_cor != NULL) $dado[]['Cor']         = $os_cam_cor;
if ($os_cam_cmb != NULL) $dado[]['Combustivel'] = $os_cam_cmb;
if ($os_cam_mar != NULL) $dado[]['Marca']       = $os_cam_mar;
if ($os_cam_mod != NULL) $dado[]['Modelo']      = $os_cam_mod;
if ($os_cam_cli != NULL) $dado[]['Clientes']    = $os_cam_cli;
if ($os_cam_ema != NULL) $dado[]['Email']       = $os_cam_ema;

$dados = array('Parametros' => $dado);

## Inicio do Temporizador ##
$Fim       = 0;
$Inicio    = date('H:i:s');
$primeiro  = 0;
$Extraidos = 0;

if ($saida != 'Texto') {
   salvaLog(array($Inicio . ' - Inicio da Sincronizacao (' .$pcodemp . ')'));
} else {
   salvaLog(array($Inicio . ' - Inicio da Extracao (' .$pcodemp . ')'));
}

do {
   ## Busca ultima passagem
   if ($saida != 'Texto') {
      if ($implanta == 0) {
         $parametros = array('pservico' => 'wfcusu',
                             'pmetodo'  => 'BuscaUsuario',
                             'pemail'   => $pcodemp
                            );
         $salvaRet = enviaDados($url, $parametros);
         $vuser    = $salvaRet->ProDataSet;
         $ultpsg   = 0;
         $user     = '';

         if (property_exists($vuser, 'ttfcusu')) {
            $ultpsg       = $vuser->ttfcusu[0]->ultpsg;
            //$ultpsg_geral = $vuser->ttfcusu[0]->ultpsg;
            $ultpsg_geral = $ultpsg;
            $user         = $vuser->ttfcusu[0]->nome;
            

            if ($primeiro == 0) {
               salvaLog(array('Sincronizando dados para o usuário "'.$user.'" ('.$pcodemp.')'));
               salvaLog(array('Última Passagem: ' . $ultpsg_geral));
               $primeiro = 1;
            }

         } else {
            if (property_exists($vuser, 'ttretorno')) {
               $mensagens = array($vuser->ttretorno->mensagem);
            } else {
               $mensagens = array('Erro na validacao do usuario "'.$pcodemp.'"');
            }
            salvaLog($mensagens);
            exit;
         }
      }
   }

   ## Verifica a ultima passagem
   if (empty($ultpsg)) {
      $ultpsg = 0;
   }

   if ($saida != 'Texto') {
      $ultpsg = $ultpsg - $rba;
   }

   if ($ultpsg < 0) {
      $ultpsg = 0;
   }

   $os_con_ord_1 = $os_con_ord;
   if ($os_con_ord_1[count($os_con_ord_1) - 1]['OpLogico'] == '') {
      $os_con_ord_1[count($os_con_ord_1) - 1]['OpLogico'] = 'AND';
   }

   $os = array();

   if (array_key_exists('idtipo', $os_cam_ord)) {
      if ($os_cam_ord['idtipo'] == 'S') {
         $os['Tipo'] = 'S';
      }
   }

   if ($ForPas == 'I') {
      $os['Tipo'] = 'I';
   }

   $os['Campo'] = utf8_encode($os_cam_ord['idipas']);
   $os['Operador'] = '>';
   $os['Valor'] = ($ForPas == 'I' ? intval($ultpsg) : $ultpsg);
   $os['OpLogico'] = '';
   $os_con_ord_1[] = $os;

   ## Verifica Periodo
   if (!empty($dataLimite)) { // && $saida != 'Texto') {
      if ($os_con_ord_1[count($os_con_ord_1) - 1]['OpLogico'] == '') {
         $os_con_ord_1[count($os_con_ord_1) - 1]['OpLogico'] = 'AND';
      }

      $os = array();
      $os['Campo'] = utf8_encode($os_cam_ord['dtpsg']);
      $os['Operador'] = '>';

      if (TIPO_DB == 'ACCESS' || 'DBASE') {
         $os['Valor'] = '1/1/'.$dataLimite;
      } elseif (TIPO_DB == 'FIREBIRD') {
         $os['Valor'] = '01/01/'.$dataLimite;
      } else {
         $os['Valor'] = $dataLimite.'-01-01';
      }

      $os['OpLogico'] = '';
      $os_con_ord_1[] = $os;

      $sql_os = sqlret($os_tab_ord, $os_cam_ord, $os_con_ord_1, $os_ord_ord);
   } else {
      $sql_os = sqlret($os_tab_ord, $os_cam_ord, $os_con_ord_1, $os_ord_ord);
      $sql_os = str_replace('  ', ' ', $sql_os);
      $sql_os = str_replace('AND ORDER', 'ORDER', $sql_os);
   }

   /* Busca a ultima passagem do DB */
   $sql_os_ult = 'SELECT'
               . ' ' . $os_cam_ord['dtpsg'] . ','
               . ' ' . $os_cam_ord['idipas'] . ','
               . ' max(' . $os_cam_ord['dtpsg'] . ') AS Ultima_Data,'
               . ' max(' . $os_cam_ord['idipas'] . ') AS Ultima_OS'
               . ' FROM ' . $os_tab_ord
               . ' GROUP BY'
               . ' ' . $os_cam_ord['dtpsg'] . ','
               . ' ' . $os_cam_ord['idipas']
               . ' ORDER BY'
               . ' ' . $os_cam_ord['dtpsg'] . ' DESC ,'
               . ' ' . $os_cam_ord['idipas']  . ' DESC'
               ;

   if ($TipoConexao == 'PDO') {
      try {
         if ($os_pdo_ord !== NULL) {
            $Conn = Conecta(TIPO_DB, $os_pdo_ord->Server, $os_pdo_ord->Data, $os_pdo_ord->User, $os_pdo_ord->Password, $os_pdo_ord->Port);
         } else {
            $Conn = Conecta(TIPO_DB, $PDO->Server, $PDO->Data, $PDO->User, $PDO->Password, $PDO->Port);
         }

      } catch (Exception $e) {
         salvaLog(array("Erro ao tentar conectar PDO (Pas_U): " . htmlentities($e->getMessage())));
         exit;
      }
      $result = Consulta($Conn, $sql_os_ult);
      unset($Conn);
   } else {
      ## Conecta ao ODBC (Padrão)
      $conn = odbc_connect($odbc_name, "", "");

      if (odbc_error()) {
         salvaLog(array("Erro ao tentar conectar ODBC (Padrão): " . odbc_errormsg($conn)));
         exit;
      }

      ## Conecta ao ODBC
      $conn_ord_ult = odbc_connect($os_odb_ord !== NULL ? $os_odb_ord : $odbc_name, "", "");

      if (odbc_error()) {
         salvaLog(array("Erro ao tentar conectar ODBC (Pas_U): " . odbc_errormsg($conn_ord_ult)));
         exit;
      }

      $exec = odbc_exec($conn_ord_ult, $sql_os_ult);

      if (odbc_error()) {
         $mensagens = array();
         $mensagens[] = "SQL (Ult): " . $sql_os_ult;
         $mensagens[] = "ERRO ODBC (Ult): " . odbc_errormsg($conn_ord_ult);
         salvaLog($mensagens);
         exit;
      }
      $result = array();
      while ($tmp = odbc_fetch_array($exec)) {
         $result[] = $tmp;
      }

      odbc_close($conn_ord_ult);
   }
 
   $Ultima_os = $result['ULTIMA_OS'];

   if (array_key_exists('ULTIMA_OS', $result)) {
      $Ultima_os = $result['ULTIMA_OS'];
   } elseif (array_key_exists('ultima_os', $result)) {
      $Ultima_os = $result['ultima_os'];
   } elseif (array_key_exists('Ultima_OS', $result)) {
      $Ultima_os = $result['Ultima_OS'];
   } else {
      $Ultima_os = 999999999;
   }

   for ($id=0; $id < $regs; $id++) { 
      if ($TipoConexao == 'PDO') {
         try {
            $Conn = Conecta(TIPO_DB, $PDO->Server, $PDO->Data, $PDO->User, $PDO->Password, $PDO->Port);
         } catch (Exception $e) {
            salvaLog(array("Erro ao tentar conectar PDO (Pas): " . htmlentities($e->getMessage())));
            exit;
         }

         $ord = Consulta($Conn, $sql_os, $id);
         unset($Conn);
      } else {
         ## Conecta ao ODBC
         $conn_ord = odbc_connect($os_odb_ord !== NULL ? $os_odb_ord : $odbc_name, "", "");
         
         if (odbc_error()) {
            salvaLog(array("Erro ao tentar conectar ODBC (Pas): " . odbc_errormsg($conn_ord)));
            exit;
         }

         $exec_ord = odbc_exec($conn_ord, $sql_os);

         if (odbc_error()) {
            $mensagens = array();
            $mensagens[] = "SQL (Pas): " . $sql_os;
            $mensagens[] = "ERRO ODBC (Pas): " . odbc_errormsg($conn_ord);
            salvalog($mensagens);
            exit;
         }

         ## Busca Passagens
         $ord = odbc_fetch_array($exec_ord, $id);
         odbc_close($conn_ord);
   
      }
      
      $row_ord = array();
      foreach ($ord as $k => $v) {
         $row_ord[$k] = $v;
      }

      ## Valida numero de registros
      if ($regs > 0 && $id >= $regs) {
         break;
      }

      foreach ($os_cam_ord as $key => $value) {
         $row_ord = change_key($row_ord, $value, $key);
      }

      $os_id = $row_ord['idipas'];

      if (empty($os_id)) {
         break;
      }

      $os_nome   = $row_ord['nome'];
      $os_codcli = $row_ord['codcli'];
      
      ## Verifica se o registro ja se encontra cadastrado
      if ($saida !== 'Texto') {
         $parametros = array('pservico' => 'wfcpas',
                             'pmetodo'  => 'VerOS',
                             'pcodemp'  => $pcodemp,
                             'pidipas'  => $os_id
                            );
         $salvaRet = enviaDados($url, $parametros);
         $veros    = $salvaRet;

         if (property_exists($veros, 'integrado')) {
            if ($veros->integrado == 'Sim') {
               $ultpsg = $os_id;
               continue;
            }
         }
      }

      $vei_placa = $row_ord['placa'];
      $serv[$id]['os'] = $row_ord;

      ## Relatos
      $NRel = 0;
      if ($os_tab_rel != NULL && $os_id != NULL && $os_id != "") {

         $sql_rel = sprintf(sqlret($os_tab_rel, $os_cam_rel, $os_con_rel, $os_ord_rel), $os_id);
         if ($TipoConexao == 'PDO') {
            try {
               if ($os_pdo_rel !== NULL) {
                  $Conn = Conecta(TIPO_DB, $os_pdo_rel->Server, $os_pdo_rel->Data, $os_pdo_rel->User, $os_pdo_rel->Password, $os_pdo_rel->Port);
               } else {
                  $Conn = Conecta(TIPO_DB, $PDO->Server, $PDO->Data, $PDO->User, $PDO->Password, $PDO->Port);
               }
            } catch (Exception $e) {
               salvaLog(array("Erro ao tentar conectar PDO (Rel): " . htmlentities($e->getMessage())));
               exit;
            }
   
            $rel = Consulta($Conn, $sql_rel);
            unset($Conn);
         } else {
            ## Conecta ao ODBC
            if ($os_odb_rel !== NULL && $os_odb_rel !== $odbc_name) {
               $conn_rel = odbc_connect($os_odb_rel, "", "");

               if (odbc_error()) {
                  salvaLog(array("Erro ao tentar conectar ODBC (Rel): " . odbc_errormsg($conn_rel)));
                  exit;
               }
            } else {
               $conn_rel = $conn;
            }

            $exec_rel = odbc_exec($conn_rel, $sql_rel);

            if (odbc_error()) {
               $mensagens = array();
               $mensagens[] = "SQL (Rel): " . $sql_rel;
               $mensagens[] = "ERRO ODBC (Rel): " . odbc_errormsg($conn_rel);
               salvaLog($mensagens);
               exit;
            }

            $rel = array();
            while ($tmp = odbc_fetch_array($exec_rel)) {
               $rel[] = $tmp;
            }

            odbc_close($conn_rel);
         }

         $NRel = count($rel);

         for ($x=0; $x < count($rel); $x++) { 
            $row_rel = array();
            foreach ($rel[$x] as $k => $v) {
               $row_rel[$k] = $v;
            }
      
            foreach ($os_cam_rel as $key => $value) {
               $row_rel = change_key($row_rel, $value, $key);
            }

            $serv[$id]['os'][$id]['relatos'][] = $row_rel;
         }

      }

      ## Servicos
      $NSer = 0;
      if ($os_tab_ser !== NULL && $os_id !== NULL && $os_id !== "") {
         $sql_ser = sprintf(sqlret($os_tab_ser, $os_cam_ser, $os_con_ser, $os_ord_ser), $os_id);

         if ($TipoConexao == 'PDO') {
            try {
               if ($os_pdo_ser !== NULL) {
                  $Conn = Conecta(TIPO_DB, $os_pdo_ser->Server, $os_pdo_ser->Data, $os_pdo_ser->User, $os_pdo_ser->Password, $os_pdo_ser->Port);
               } else {
                  $Conn = Conecta(TIPO_DB, $PDO->Server, $PDO->Data, $PDO->User, $PDO->Password, $PDO->Port);
               }
            } catch (Exception $e) {
               salvaLog(array("Erro ao tentar conectar PDO (Ser): " . htmlentities($e->getMessage())));
               exit;
            }
   
            $ser = Consulta($Conn, $sql_ser);
            unset($Conn);
         } else {
            ## Conecta ao ODBC
            if ($os_odb_ser !== NULL && $os_odb_ser !== $odbc_name) {
               $conn_ser = odbc_connect($os_odb_ser, "", "");

               if (odbc_error()) {
                  salvaLog(array("Erro ao tentar conectar ODBC (Ser): " . odbc_errormsg($conn_ser)));
                  exit;
               }
            } else {
               $conn_ser = $conn;
            }

            $exec_ser = odbc_exec($conn_ser, $sql_ser);

            if (odbc_error()) {
               $mensagens = array();
               $mensagens[] = "SQL (Ser): " . $sql_ser;
               $mensagens[] = "ERRO ODBC (Ser): " . odbc_errormsg($conn_ser);
               salvaLog($mensagens);
               exit;
            }

            $ser = array();
            while ($tmp = odbc_fetch_array($exec_ser)) {
               $ser[] = $tmp;
            }

            odbc_close($conn_ser);
         }

         $NSer = count($ser);

         for ($x=0; $x < count($ser); $x++) { 
            $row_ser = array();
            foreach ($ser[$x] as $k => $v) {
               $row_ser[$k] = $v;
            }

            foreach ($os_cam_ser as $key => $value) {
               $row_ser = change_key($row_ser, $value, $key);
            }
            
            ## Servico
            $servid = $row_ser['codser'];

            if ($os_tab_sev !== NULL) {
               $sql_sev = sprintf(sqlret($os_tab_sev, $os_cam_sev, $os_con_sev, $os_ord_sev), $servid);

               if ($TipoConexao == 'PDO') {
                  try {
                     if ($os_pdo_sev !== NULL) {
                        $Conn = Conecta(TIPO_DB, $os_pdo_sev->Server, $os_pdo_sev->Data, $os_pdo_sev->User, $os_pdo_sev->Password, $os_pdo_sev->Port);
                     } else {
                        $Conn = Conecta(TIPO_DB, $PDO->Server, $PDO->Data, $PDO->User, $PDO->Password, $PDO->Port);
                     }
                  } catch (Exception $e) {
                     salvaLog(array("Erro ao tentar conectar PDO (Sev): " . htmlentities($e->getMessage())));
                     exit;
                  }
         
                  $sev = Consulta($Conn, $sql_sev);
                  unset($Conn);
               } else {
                  ## Conecta ao ODBC
                  if ($os_odb_sev !== NULL && $os_odb_sev !== $odbc_name) {
                     $conn_sev = odbc_connect($os_odb_sev, "", "");

                     if (odbc_error()) {
                        salvaLog(array("Erro ao tentar conectar ODBC (Sev): " . odbc_errormsg($conn_sev)));
                        exit;
                     }
                  } else {
                     $conn_sev = $conn;
                  }

                  $exec_sev = odbc_exec($conn_sev, $sql_sev);

                  if (odbc_error()) {
                     $mensagens = array();
                     $mensagens[] = "SQL (Sev): " . $sql_sev;
                     $mensagens[] = "ERRO ODBC (Sev): " . odbc_errormsg($conn_sev);
                     salvaLog($mensagens);
                     exit;
                  }

                  $sev = array();
                  while ($tmp = odbc_fetch_array($exec_sev)) {
                     $sev[] = $tmp;
                  }
                  odbc_close($conn_sev);
               }

               $row_sev = array();
               foreach ($sev[0] as $k => $v) {
                  $row_sev[$k] = $v;
               }
      
               foreach($os_cam_sev as $key => $value) {
                  $row_sev = change_key($row_sev, $value, $key);
               }
               
               if (array_key_exists('descri', $os_cam_sev)) {
                  $row_ser['descri'] = $row_sev['descri'];
               }

               if (array_key_exists('quant', $os_cam_sev)) {
                  $row_ser['quant'] = $row_sev['quant'];
               }
               $serv[$id]['os']['servicos'][] = $row_ser;
            } else {
               $serv[$id]['os']['servicos'][] = $row_ser;
            }
         }
      }

      ## Servicos Terceiros
      $NTer = 0;
      if ($os_tab_ter != NULL && $os_id != NULL && $os_id != "") {
         
         $sql_ter = sprintf(sqlret($os_tab_ter, $os_cam_ter, $os_con_ter, $os_ord_ter), $os_id);
         if ($TipoConexao == 'PDO') {
            try {
               if ($os_pdo_ter !== NULL) {
                  $Conn = Conecta(TIPO_DB, $os_pdo_ter->Server, $os_pdo_ter->Data, $os_pdo_ter->User, $os_pdo_ter->Password, $os_pdo_ter->Port);
               } else {
                  $Conn = Conecta(TIPO_DB, $PDO->Server, $PDO->Data, $PDO->User, $PDO->Password, $PDO->Port);
               }
            } catch (Exception $e) {
               salvaLog(array("Erro ao tentar conectar PDO (Ter): " . htmlentities($e->getMessage())));
               exit;
            }
   
            $ter = Consulta($Conn, $sql_ter);
            unset($Conn);
         } else {
            ## Conecta ao ODBC
            if ($os_odb_ter !== NULL && $os_odb_ter !== $odbc_name) {
               $conn_ter = odbc_connect($os_odb_ter, "", "");

               if (odbc_error()) {
                  salvaLog(array("Erro ao tentar conectar ODBC (Ter): " . odbc_errormsg($conn_ter)));
                  exit;
               }
            } else {
               $conn_ter = $conn;
            }

            $exec_ter = odbc_exec($conn_ter, $sql_ter);

            if (odbc_error()) {
               $mensagens = array();
               $mensagens[] = "SQL (Ter): " . $sql_ter;
               $mensagens[] = "ERRO ODBC (Ter): " . odbc_errormsg($conn_ter);
               salvaLog($mensagens);
               exit;
            }
            
            $ter = array();
            while ($tmp = odbc_fetch_array($exec_ter)) {
               $ter[] = $tmp;
            }
            odbc_close($conn_ter);
         }

         $NTer = count($ter);

         for ($x=0; $x < count($ter); $x++) { 
            $row_ter = array();
            foreach ($ter[$x] as $k => $v) {
               $row_ter[$k] = $v;
            }
      
            foreach ($os_cam_ter as $key => $value) {
               $row_ter = change_key($row_ter, $value, $key);
            }

            $servid = $row_ter['codser'];

            ## Servico
            if ($os_tab_sev !== NULL) {
               $sql_sev = sprintf(sqlret($os_tab_sev, $os_cam_sev, $os_con_sev, $os_ord_sev), $servid);
               
               if ($TipoConexao == 'PDO') {
                  try {
                     if ($os_pdo_sev !== NULL) {
                        $Conn = Conecta(TIPO_DB, $os_pdo_sev->Server, $os_pdo_sev->Data, $os_pdo_sev->User, $os_pdo_sev->Password, $os_pdo_sev->Port);
                     } else {
                        $Conn = Conecta(TIPO_DB, $PDO->Server, $PDO->Data, $PDO->User, $PDO->Password, $PDO->Port);
                     }
                  } catch (Exception $e) {
                     salvaLog(array("Erro ao tentar conectar PDO (TSev): " . htmlentities($e->getMessage())));
                     exit;
                  }
         
                  $sev = Consulta($Conn, $sql_sev);
                  unset($Conn);
               } else {
                  ## Conecta ao ODBC
                  if ($os_odb_sev !== NULL && $os_odb_sev !== $odbc_name) {
                     $conn_sev = odbc_connect($os_odb_sev, "", "");

                     if (odbc_error()) {
                        salvaLog(array("Erro ao tentar conectar ODBC (TSev): " . odbc_errormsg($conn_sev)));
                        exit;
                     }
                  } else {
                     $conn_sev = $conn;
                  }

                  $exec_sev = odbc_exec($conn_sev, $sql_sev);

                  if (odbc_error()) {
                     $mensagens = array();
                     $mensagens[] = "SQL (TSev): " . $sql_sev;
                     $mensagens[] = "ERRO ODBC (TSev): " . odbc_errormsg($conn_sev);
                     salvaLog($mensagens);
                     exit;
                  }

                  $sev = array();
                  while ($tmp = odbc_fetch_array($exec_sev)) {
                     $sev[] = $tmp;
                  }
                  odbc_close($conn_sev);
               }

               $row_sev = array();
               foreach ($sev[0] as $k => $v) {
                  $row_sev[$k] = $v;
               }
                     
               foreach($os_cam_sev as $key => $value) {
                  $row_sev = change_key($row_sev, $value, $key);
               }
               
               if (array_key_exists('descri', $os_cam_sev)) {
                  $row_ter['descri'] = $row_sev['descri'];
               }

               if (array_key_exists('quant', $os_cam_sev)) {
                  $row_ter['quant'] = $row_sev['quant'];
               }
               $serv[$id]['os']['servicos'][] = $row_ter;
            } else {
               $serv[$id]['os']['servicos'][] = $row_ter;
            }
         }
      }

      ## Pecas
      if ($os_tab_pec !== NULL && $os_id !== NULL && $os_id !== "") {
         $sql_pec = sprintf(sqlret($os_tab_pec, $os_cam_pec, $os_con_pec, $os_ord_pec), $os_id);
         
         if ($TipoConexao == 'PDO') {
            try {
               if ($os_pdo_pec !== NULL) {
                  $Conn = Conecta(TIPO_DB, $os_pdo_pec->Server, $os_pdo_pec->Data, $os_pdo_pec->User, $os_pdo_pec->Password, $os_pdo_pec->Port);
               } else {
                  $Conn = Conecta(TIPO_DB, $PDO->Server, $PDO->Data, $PDO->User, $PDO->Password, $PDO->Port);
               }
            } catch (Exception $e) {
               salvaLog(array("Erro ao tentar conectar PDO (Pec): " . htmlentities($e->getMessage())));
               exit;
            }
   
            $pec = Consulta($Conn, $sql_pec);
            unset($Conn);
         } else {
            ## Conecta ao ODBC
            if ($os_odb_pec !== NULL && $os_odb_pec !== $odbc_name) {
               $conn_pec = odbc_connect($os_odb_pec, "", "");

               if (odbc_error()) {
                  salvaLog(array("Erro ao tentar conectar ODBC (Pec): " . odbc_errormsg($conn_pec)));
                  exit;
               }
            } else {
               $conn_pec = $conn;
            }

            $exec_pec = odbc_exec($conn_pec, $sql_pec);

            if (odbc_error()) {
               $mensagens = array();
               $mensagens[] = "SQL (Pec): " . $sql_pec;
               $mensagens[] = "ERRO ODBC (Pec): " . odbc_errormsg($conn_pec);
               salvaLog($mensagens);
               exit;
            }

            $pec = array();
            while ($tmp = odbc_fetch_array($exec_pec)) {
               $pec[] = $tmp;
            }

            odbc_close($conn_pec);
         }

         $NPec = count($pec);

         for ($x=0; $x < count($pec); $x++) {
            $row_pec = array();
            foreach ($pec[$x] as $k => $v) {
               $row_pec[$k] = $v;
            }
      
            foreach ($os_cam_pec as $key => $value) {
               $row_pec = change_key($row_pec, $value, $key);
            }

            $prodid = $row_pec['codpec'];
            $prodid = str_replace("'", '', $prodid);
            $prodid = str_replace('"', '', $prodid);

            ## Produtos
            if ($os_tab_pro !== NULL && $prodid !== NULL && $prodid !== "") {
               $sql_pro = sprintf(sqlret($os_tab_pro, $os_cam_pro, $os_con_pro, $os_ord_pro), $prodid);
               
               if ($TipoConexao == 'PDO') {
                  try {
                     if ($os_pdo_pro !== NULL) {
                        $Conn = Conecta(TIPO_DB, $os_pdo_pro->Server, $os_pdo_pro->Data, $os_pdo_pro->User, $os_pdo_pro->Password, $os_pdo_pro->Port);
                     } else {
                        $Conn = Conecta(TIPO_DB, $PDO->Server, $PDO->Data, $PDO->User, $PDO->Password, $PDO->Port);
                     }
                  } catch (Exception $e) {
                     salvaLog(array("Erro ao tentar conectar PDO (Pro): " . htmlentities($e->getMessage())));
                     exit;
                  }
         
                  $pro = Consulta($Conn, $sql_pro);
                  unset($Conn);
               } else {
                  ## Conecta ao ODBC
                  if ($os_odb_pro !== NULL && $os_odb_pro !== $odbc_name) {
                     $conn_pro = odbc_connect($os_odb_pro, "", "");

                     if (odbc_error()) {
                        salvaLog(array("Erro ao tentar conectar ODBC (Pro): " . odbc_errormsg($conn_pro)));
                        exit;
                     }
                  } else {
                     $conn_pro = $conn;
                  }

                  $exec_pro = odbc_exec($conn_pro, $sql_pro);

                  if (odbc_error()) {
                     $mensagens = array();
                     $mensagens[] = "SQL (Pro): " . $sql_pro;
                     $mensagens[] = "ERRO ODBC (Pro): " . odbc_errormsg($conn_pro);
                     salvaLog($mensagens);
                     exit;
                  }

                  $pro = array();
                  while ($tmp = odbc_fetch_array($exec_pro)) {
                     $pro[] = $tmp;
                  }
                  odbc_close($conn_pro);
               }

               $row_pro = array();
               foreach ($pro[0] as $k => $v) {
                  $row_pro[$k] = $v;
               }
                     
               foreach ($os_cam_pro as $key => $value) {
                  $row_pro = change_key($row_pro, $value, $key);
               }

               if (array_key_exists('quant', $os_cam_pro)) {
                  $row_pec['quant'] = $row_pro['quant'];
               }

               if (array_key_exists('descri', $os_cam_pro)) {
                  $row_pec['descri'] = $row_pro['descri'];
               }

               $serv[$id]['os']['pecas'][] = $row_pec;
            } else {
               $serv[$id]['os']['pecas'][] = $row_pec;
            }
         }
      }

      ## Desconsidera passagens sem pecas ou servicos
      if ($NRel <= 0 && $NSer <= 0 && $NTer <= 0 && $NPec <= 0) {
         continue;
      }

      ## Veiculo
      if ($os_tab_vei != NULL && (($os_id !== NULL && $os_id !== "") || ($vei_placa !== NULL && $vei_placa !== "")) ) {
         $sql_vei = sprintf(sqlret($os_tab_vei, $os_cam_vei, $os_con_vei, $os_ord_vei), ($os_con_vei[0]['Tipo'] == 'P' ? $vei_placa : $os_id) );

         if ($TipoConexao == 'PDO') {
            try {
               if ($os_pdo_vei !== NULL) {
                  $Conn = Conecta(TIPO_DB, $os_pdo_vei->Server, $os_pdo_vei->Data, $os_pdo_vei->User, $os_pdo_vei->Password, $os_pdo_vei->Port);
               } else {
                  $Conn = Conecta(TIPO_DB, $PDO->Server, $PDO->Data, $PDO->User, $PDO->Password, $PDO->Port);
               }
            } catch (Exception $e) {
               salvaLog(array("Erro ao tentar conectar PDO (Vei): " . htmlentities($e->getMessage())));
               exit;
            }
   
            $vei = Consulta($Conn, $sql_vei);
            unset($Conn);
         } else {
            ## Conecta ao ODBC
            if ($os_odb_vei !== NULL && $os_odb_vei !== $odbc_name) {
               $conn_vei = odbc_connect($os_odb_vei, "", "");

               if (odbc_error()) {
                  salvaLog(array("Erro ao tentar conectar ODBC (Vei): " . odbc_errormsg($conn_vei)));
                  exit;
               }
            } else {
               $conn_vei = $conn;
            }

            $exec_vei = odbc_exec($conn_vei, $sql_vei);

            if (odbc_error()) {
               $mensagens = array();
               $mensagens[] = "SQL (Vei): " . $sql_vei;
               $mensagens[] = "ERRO ODBC (Vei): " . odbc_errormsg($conn_vei);
               salvaLog($mensagens);
               exit;
            }

            $vei = array();
            while ($tmp = odbc_fetch_array($exec_vei)) {
               $vei[] = $tmp;
            }

            odbc_close($conn_vei);
         }

         for ($x=0; $x < count($vei); $x++) {
            $row_vei = array();
            foreach ($vei[$x] as $k => $v) {
               $row_vei[$k] = $v;
            }
      
            foreach($os_cam_vei as $key => $value) {
               $row_vei = change_key($row_vei, $value, $key);
            }

            $serv[$id]['os']['veiculo'][$x] = $row_vei;
            
            $cli_id = $row_vei['codcli'];
            $cor_id = $row_vei['corvei'];
            $cmb_id = $row_vei['cmbvei'];
            $mar_id = $row_vei['marca'];
            $mod_id = $row_vei['modelo'];

            ## Cor
            if ($os_tab_cor != NULL && $cor_id != NULL && $cor_id != "") {
               $sql_cor = sprintf(sqlret($os_tab_cor, $os_cam_cor, $os_con_cor, $os_ord_cor), $cor_id);
               
               if ($TipoConexao == 'PDO') {
                  try {
                     if ($os_pdo_cor !== NULL) {
                        $Conn = Conecta(TIPO_DB, $os_pdo_cor->Server, $os_pdo_cor->Data, $os_pdo_cor->User, $os_pdo_cor->Password, $os_pdo_cor->Port);
                     } else {
                        $Conn = Conecta(TIPO_DB, $PDO->Server, $PDO->Data, $PDO->User, $PDO->Password, $PDO->Port);
                     }
                  } catch (Exception $e) {
                     salvaLog(array("Erro ao tentar conectar PDO (Cor): " . htmlentities($e->getMessage())));
                     exit;
                  }
         
                  $cor = Consulta($Conn, $sql_cor);
                  unset($Conn);
               } else {
                  ## Conecta ao ODBC
                  if ($os_odb_cor !== NULL && $os_odb_cor !== $odbc_name) {
                     $conn_cor = odbc_connect($os_odb_cor, "", "");

                     if (odbc_error()) {
                        salvaLog(array("Erro ao tentar conectar ODBC (Cor): " . odbc_errormsg($conn_cor)));
                        exit;
                     }
                  } else {
                     $conn_cor = $conn;
                  }

                  $exec_cor = odbc_exec($conn_cor, $sql_cor);

                  if (odbc_error()) {
                     $mensagens = array();
                     $mensagens[] = "SQL (Cor): " . $sql_cor;
                     $mensagens[] = "ERRO ODBC (Cor): " . odbc_errormsg($conn_cor);
                     salvaLog($mensagens);
                     exit;
                  }
                  
                  $cor = array();
                  while ($tmp = odbc_fetch_array($exec_cor)) {
                     $cor[] = $tmp;
                  }
                  odbc_close($conn_cor);
               }

               $row_cor = array();
               foreach ($cor[0] as $k => $v) {
                  $row_cor[$k] = $v;
               }
                     
               foreach($os_cam_cor as $key => $value) {
                  $row_cor = change_key($row_cor, $value, $key);
               }

               if (array_key_exists('descri', $row_cor)) {
                  $serv[$id]['os']['veiculo'][$x]['corvei'] = $row_cor['descri'];
               }
            }

            ## Combustivel
            if ($os_tab_cmb!= NULL && $cmb_id != NULL && $cmb_id != "") {
               $sql_cmb = sprintf(sqlret($os_tab_cmb, $os_cam_cmb, $os_con_cmb, $os_ord_cmb), $cmb_id);
               
               if ($TipoConexao == 'PDO') {
                  try {
                     if ($os_pdo_cmb !== NULL) {
                        $Conn = Conecta(TIPO_DB, $os_pdo_cmb->Server, $os_pdo_cmb->Data, $os_pdo_cmb->User, $os_pdo_cmb->Password, $os_pdo_cmb->Port);
                     } else {
                        $Conn = Conecta(TIPO_DB, $PDO->Server, $PDO->Data, $PDO->User, $PDO->Password, $PDO->Port);
                     }
                  } catch (Exception $e) {
                     salvaLog(array("Erro ao tentar conectar PDO (Cmb): " . htmlentities($e->getMessage())));
                     exit;
                  }
         
                  $cmb = Consulta($Conn, $sql_cmb);
                  unset($Conn);
               } else {
                  ## Conecta ao ODBC
                  if ($os_odb_cmb !== NULL && $os_odb_cmb !== $odbc_name) {
                     $conn_cmb = odbc_connect($os_odb_cmb, "", "");

                     if (odbc_error()) {
                        salvaLog(array("Erro ao tentar conectar ODBC (Cmb): " . odbc_errormsg($conn_cmb)));
                        exit;
                     }
                  } else {
                     $conn_cmb = $conn;
                  }

                  $exec_cmb = odbc_exec($conn, $sql_cmb);

                  if (odbc_error()) {
                     $mensagens = array();
                     $mensagens[] = "SQL (Cor): " . $sql_cmb;
                     $mensagens[] = "ERRO ODBC (Cor): " . odbc_errormsg($conn_cmb);
                     salvaLog($mensagens);
                     exit;
                  }
                  
                  $cmb = array();
                  while ($tmp = odbc_fetch_array($exec_cmb)) {
                     $cmb[] = $tmp;
                  }
                  odbc_close($conn_cmb);
               }

               $row_cmb = array();
               foreach ($cmb[0] as $k => $v) {
                  $row_cmb[$k] = $v;
               }
                     
               foreach($os_cam_cmb as $key => $value) {
                  $row_cmb = change_key($row_cmb, $value, $key);
               }

               if (array_key_exists('descri', $row_cmb)) {
                  $serv[$id]['os']['veiculo'][$x]['cmbvei'] = $row_cmb['descri'];
               }
            }

            ## Marca
            if ($os_tab_mar!= NULL && $mar_id != NULL && $mar_id != "") {
               $sql_mar = sprintf(sqlret($os_tab_mar, $os_cam_mar, $os_con_mar, $os_ord_mar), $mar_id);
               
               if ($TipoConexao == 'PDO') {
                  try {
                     if ($os_pdo_mar !== NULL) {
                        $Conn = Conecta(TIPO_DB, $os_pdo_mar->Server, $os_pdo_mar->Data, $os_pdo_mar->User, $os_pdo_mar->Password, $os_pdo_mar->Port);
                     } else {
                        $Conn = Conecta(TIPO_DB, $PDO->Server, $PDO->Data, $PDO->User, $PDO->Password, $PDO->Port);
                     }
                  } catch (Exception $e) {
                     salvaLog(array("Erro ao tentar conectar PDO (Mar): " . htmlentities($e->getMessage())));
                     exit;
                  }
         
                  $mar = Consulta($Conn, $sql_mar);
                  unset($Conn);
               } else {
                  ## Conecta ao ODBC
                  if ($os_odb_mar !== NULL && $os_odb_mar !== $odbc_name) {
                     $conn_mar = odbc_connect($os_odb_mar, "", "");

                     if (odbc_error()) {
                        salvaLog(array("Erro ao tentar conectar ODBC (Mar): " . odbc_errormsg($conn_mar)));
                        exit;
                     }
                  } else {
                     $conn_mar = $conn;
                  }

                  $exec_mar = odbc_exec($conn_mar, $sql_mar);

                  if (odbc_error()) {
                     $mensagens = array();
                     $mensagens[] = "SQL (Mar): " . $sql_mar;
                     $mensagens[] = "ERRO ODBC (Mar): " . odbc_errormsg($conn_mar);
                     salvaLog($mensagens);
                     exit;
                  }
                  
                  $mar = array();
                  while ($tmp = odbc_fetch_array($exec_mar)) {
                     $mar[] = $tmp;
                  }
                  odbc_close($conn_mar);
               }

               $row_mar = array();
               foreach ($mar[0] as $k => $v) {
                  $row_mar[$k] = $v;
               }
               
               foreach($os_cam_mar as $key => $value) {
                  $row_mar = change_key($row_mar, $value, $key);
               }

               if (array_key_exists('descri', $row_mar)) {
                  $serv[$id]['os']['veiculo'][$x]['marca'] = $row_mar['descri'];
               }
            }

            ## Modelo
            if ($os_tab_mod!= NULL && $mod_id != NULL && $mod_id != "") {
               $sql_mod = sprintf(sqlret($os_tab_mod, $os_cam_mod, $os_con_mod, $os_ord_mod), $mod_id);
               
               if ($TipoConexao == 'PDO') {
                  try {
                     if ($os_pdo_mod !== NULL) {
                        $Conn = Conecta(TIPO_DB, $os_pdo_mod->Server, $os_pdo_mod->Data, $os_pdo_mod->User, $os_pdo_mod->Password, $os_pdo_mod->Port);
                     } else {
                        $Conn = Conecta(TIPO_DB, $PDO->Server, $PDO->Data, $PDO->User, $PDO->Password, $PDO->Port);
                     }
                  } catch (Exception $e) {
                     salvaLog(array("Erro ao tentar conectar PDO (Mod): " . htmlentities($e->getMessage())));
                     exit;
                  }
         
                  $mod = Consulta($Conn, $sql_mod);
                  unset($Conn);
               } else {
                  ## Conecta ao ODBC
                  if ($os_odb_mod !== NULL && $os_odb_mod !== $odbc_name) {
                     $conn_mod = odbc_connect($os_odb_mod, "", "");

                     if (odbc_error()) {
                        salvaLog(array("Erro ao tentar conectar ODBC (Mod): " . odbc_errormsg($conn_mod)));
                        exit;
                     }
                  } else {
                     $conn_mod = $conn;
                  }

                  $exec_mod = odbc_exec($conn_mod, $sql_mod);

                  if (odbc_error()) {
                     $mensagens = array();
                     $mensagens[] = "SQL (Mod): " . $sql_mod;
                     $mensagens[] = "ERRO ODBC (Mod): " . odbc_errormsg($conn_mod);
                     salvaLog($mensagens);
                     exit;
                  }
                  
                  $mod = array();
                  while ($tmp = odbc_fetch_array($exec_mod)) {
                     $mod[] = $tmp;
                  }
                  odbc_close($conn_mod);
               }

               $row_mod = array();
               foreach ($mod[0] as $k => $v) {
                  $row_mod[$k] = $v;
               }
               
               foreach($os_cam_mod as $key => $value) {
                  $row_mod = change_key($row_mod, $value, $key);
               }

               if (array_key_exists('descri', $row_mod)) {
                  $serv[$id]['os']['veiculo'][$x]['modelo'] = $row_mod['descri'];
               }
            }

            $vei_tmp = $serv[$id]['os']['veiculo'][$x];
            $descri  = '';
            if ($serv[$id]['os']['veiculo'][$x]['descri'] == '') {
               if (array_key_exists('descri', $vei_tmp) || empty($vei_tmp['descri'])) {
                  $descri .= (array_key_exists('marca', $vei_tmp) ? $vei_tmp['marca'] . ' ' : '');
                  $descri .= (array_key_exists('modelo', $vei_tmp) ? $vei_tmp['modelo'] . ' ' : '');
                  $descri .= (array_key_exists('versao', $vei_tmp) ? $vei_tmp['versao'] . ' ' : '');
                  $serv[$id]['os']['veiculo'][$x]['descri'] = $descri;
               }
            }

            ## Cliente
            if ($os_tab_cli!== NULL && $cli_id !== NULL && $cli_id !== "") {
               $sql_cli = sprintf(sqlret($os_tab_cli, $os_cam_cli, $os_con_cli, $os_ord_cli), $cli_id);
               
               if ($TipoConexao == 'PDO') {
                  try {
                     if ($os_pdo_cli !== NULL) {
                        $Conn = Conecta(TIPO_DB, $os_pdo_cli->Server, $os_pdo_cli->Data, $os_pdo_cli->User, $os_pdo_cli->Password, $os_pdo_cli->Port);
                     } else {
                        $Conn = Conecta(TIPO_DB, $PDO->Server, $PDO->Data, $PDO->User, $PDO->Password, $PDO->Port);
                     }
                  } catch (Exception $e) {
                     salvaLog(array("Erro ao tentar conectar PDO (Cli): " . htmlentities($e->getMessage())));
                     exit;
                  }
         
                  $cli = Consulta($Conn, $sql_cli);
                  unset($Conn);
               } else {
                  ## Conecta ao ODBC
                  if ($os_odb_cli !== NULL && $os_odb_cli !== $odbc_name) {
                     $conn_cli = odbc_connect($os_odb_cli, "", "");

                     if (odbc_error()) {
                        salvaLog(array("Erro ao tentar conectar ODBC (Cli): " . odbc_errormsg($conn_cli)));
                        exit;
                     }
                  } else {
                     $conn_cli = $conn;
                  }

                  $exec_cli = odbc_exec($conn_cli, $sql_cli);

                  if (odbc_error()) {
                     $mensagens = array();
                     $mensagens[] = "SQL (Cli): " . $sql_cli;
                     $mensagens[] = "ERRO ODBC (Cli): " . odbc_errormsg($conn_cli);
                     salvaLog($mensagens);
                     exit;
                  }
                  
                  $cli = array();
                  while ($tmp = odbc_fetch_array($exec_cli)) {
                     $cli[] = $tmp;
                  }
                  odbc_close($conn_cli);
               }

               for ($y=0; $y < count($cli); $y++) {
                  $row_cli = array();
                  foreach ($cli[$y] as $k => $v) {
                     $row_cli[$k] = $v;
                  }
                  
                  foreach($os_cam_cli as $key => $value) {
                     $row_cli = change_key($row_cli, $value, $key);
                  }
                  $serv[$id]['os']['cliente'][$y] = $row_cli;

                  ## Email
                  if ($os_tab_ema != NULL) {
                     $sql_ema = sprintf(sqlret($os_tab_ema, $os_cam_ema, $os_con_ema, $os_ord_ema), $cli_id);
               
                     if ($TipoConexao == 'PDO') {
                        try {
                           if ($os_pdo_ema !== NULL) {
                              $Conn = Conecta(TIPO_DB, $os_pdo_ema->Server, $os_pdo_ema->Data, $os_pdo_ema->User, $os_pdo_ema->Password, $os_pdo_ema->Port);
                           } else {
                              $Conn = Conecta(TIPO_DB, $PDO->Server, $PDO->Data, $PDO->User, $PDO->Password, $PDO->Port);
                           }
                        } catch (Exception $e) {
                           salvaLog(array("Erro ao tentar conectar PDO (Ema): " . htmlentities($e->getMessage())));
                           exit;
                        }
               
                        $ema = Consulta($Conn, $sql_ema);
                        unset($Conn);
                     } else {
                        ## Conecta ao ODBC
                        if ($os_odb_ema !== NULL && $os_odb_ema !== $odbc_name) {
                           $conn_ema = odbc_connect($os_odb_ema, "", "");

                           if (odbc_error()) {
                              salvaLog(array("Erro ao tentar conectar ODBC (ema): " . odbc_errormsg($conn_ema)));
                              exit;
                           }
                        } else {
                           $conn_ema = $conn;
                        }

                        $exec_ema = odbc_exec($conn_ema, $sql_ema);

                        if (odbc_error()) {
                           $mensagens = array();
                           $mensagens[] = "SQL (Ema): " . $sql_ema;
                           $mensagens[] = "ERRO ODBC (Ema): " . odbc_errormsg($conn_ema);
                           salvaLog($mensagens);
                           exit;
                        }
                  
                        $ema = array();
                        while ($tmp = odbc_fetch_array($exec_ema)) {
                           $ema[] = $tmp;
                        }
                        odbc_close($conn_ema);
                     }

                     $row_ema = array();
                     foreach ($ema[0] as $k => $v) {
                        $row_ema[$k] = $v;
                     }
                           
                     foreach($os_cam_ema as $key => $value) {
                        $row_ema = change_key($row_ema, $value, $key);
                     }

                     if (array_key_exists('e_mail', $row_ema)) {
                        $serv[$id]['os']['cliente'][$y]['e_mail'] = $row_ema['e_mail'];
                     }
                  }
               }

            }
            break;
         }
      }

      ## Cliente
      if ($os_tab_cli != NULL && ($os_nome != NULL && $os_nome != "") || ($os_codcli != NULL && $os_codcli != "")) {
         $sql_cli = sprintf(sqlret($os_tab_cli, $os_cam_cli, $os_con_cli, $os_ord_cli), (!empty($os_codcli) ? $os_codcli : $os_nome));
         
         if ($TipoConexao == 'PDO') {
            try {
               if ($os_pdo_cli !== NULL) {
                  $Conn = Conecta(TIPO_DB, $os_pdo_cli->Server, $os_pdo_cli->Data, $os_pdo_cli->User, $os_pdo_cli->Password, $os_pdo_cli->Port);
               } else {
                  $Conn = Conecta(TIPO_DB, $PDO->Server, $PDO->Data, $PDO->User, $PDO->Password, $PDO->Port);
               }
            } catch (Exception $e) {
               salvaLog(array("Erro ao tentar conectar PDO (Cli): " . htmlentities($e->getMessage())));
               exit;
            }
   
            $cli = Consulta($Conn, $sql_cli);
            unset($Conn);
         } else {
            ## Conecta ao ODBC
            if ($os_odb_cli !== NULL && $os_odb_cli !== $odbc_name) {
               $conn_cli = odbc_connect($os_odb_cli, "", "");

               if (odbc_error()) {
                  salvaLog(array("Erro ao tentar conectar ODBC (Cli1): " . odbc_errormsg($conn_cli)));
                  exit;
               }
            } else {
               $conn_cli = $conn;
            }

            $exec_cli = odbc_exec($conn_cli, $sql_cli);

            if (odbc_error()) {
               $mensagens = array();
               $mensagens[] = "SQL (Cli1): " . $sql_cli;
               $mensagens[] = "ERRO ODBC (Cli1): " . odbc_errormsg($conn_cli);
               salvaLog($mensagens);
               exit;
            }
                  
            $cli = array();
            while ($tmp = odbc_fetch_array($exec_cli)) {
               $cli[] = $tmp;
            }
            odbc_close($conn_cli);
         }

         $row_cli = array();
         foreach ($cli[0] as $k => $v) {
            $row_cli[$k] = $v;
         }

         foreach($os_cam_cli as $key => $value) {
            $row_cli = change_key($row_cli, $value, $key);
         }
         $serv[$id]['os']['cliente'][] = $row_cli;
      }

      if ($implanta != 0) {
         $ultpsg = $os_id;
      }

   }

   if ($saida !== 'Texto' || count($serv) <= 0) {
      salvaLog(array(count($serv) . ' Registro(s) carregados para envio... (' . $ultpsg . ')'));
      if (count($serv) <= 0) {
         exit(0);
      }
   } else {
      //salvaLog(array(count($serv) . ' Registro(s) carregados para extracao... (' . $ultpsg . ')'));
   }

   $pservico = 'wfcpas';
   $pmetodo  = 'GravaPsgSiare';
   $pidipas  = '';

   $pjsonPas = '';
   $pjsonRel = '';
   $pjsonSer = '';
   $pjsonPec = '';
   $pjsonCli = '';
   $pjsonVei = '';

   $aLimpaCampos = array('-','.','/');

   //echo '<pre>';
   //print_r($serv);
   //echo '</pre><br/>';
   //salvaLog(array(print_r($serv, true)));
   //exit;

   ## Sincroniza as Passagens com a Ficha do Carro
   for ($i = 0;$i <= count($serv); $i++) {
      $os = $serv[$i]['os'];

      $pidipas = $os['idipas'];

      if ($pidipas == "") continue;
      
      $placa = trim($os['placa']);
      
      if (empty($placa)) continue;

      $placa = str_replace('-', '', $placa);
      $placa = str_replace(' ', '', $placa);
      
      if (strlen($placa) !== 7) continue;

      ## Passagem
      $ordem = array();

      if (!array_key_exists('veiculo', $os)) {
         $ordem[0]['placa']  = (array_key_exists('placa', $os)  ? $os['placa']  : '');
         $ordem[0]['chassi'] = (array_key_exists('chassi', $os) ? $os['chassi'] : '');
         $ordem[0]['km']     = (array_key_exists('km', $os)     ? $os['km']     : '');
      } else {
         $ordem[0]['placa']  = (array_key_exists('placa', $os)  ? $os['placa']  : $os['veiculo'][0]['placa']);
         $ordem[0]['chassi'] = (array_key_exists('chassi', $os) ? $os['chassi'] : $os['veiculo'][0]['chassi']);
         $ordem[0]['km']     = (array_key_exists('km', $os)     ? $os['km']     : $os['veiculo'][0]['km']);
      }
      
      if (is_numeric($ordem[0]['km'])) {
         $ordem[0]['km'] = intval($ordem[0]['km']);
      }

      $ordem[0]['dtpsg']  = date("d/m/Y", strtotime($os['dtpsg']));

      if (!array_key_exists('cgccpf', $os['cliente'][0])) {
         if (array_key_exists('tipcli', $os['cliente'][0])) {
            $ordem[0]['cgccpf'] = ($os['cliente'][0]['tipcli'] == 'J' ? 
                                    $os['cliente'][0]['cgc'] : $os['cliente'][0]['cpf']);
         } else {
            $ordem[0]['cgccpf'] = '';
         }
      }

      $ordem[0]['cgccpf'] = str_replace($aLimpaCampos, '', $ordem[0]['cgccpf']);
      $ordem[0]['placa']  = str_replace($aLimpaCampos, '', $ordem[0]['placa']);

      $pjsonPas = retornaJson('ttfccpv', $pidipas, $ordem);

      ## Relatos
      $relatos = $os['relatos'];
      $pjsonRel = retornaJson('ttfcrpv', $pidipas, $relatos);

      ## Servicos
      $servicos = $os['servicos'];

      $ser = array();
      for ($s = 0; $s < count($servicos); $s++) {
         if (!array_key_exists('codser', $servicos[$s])) $servicos[$s]['codser'] = '';
         if (!array_key_exists('descri', $servicos[$s])) $servicos[$s]['descri'] = '';

         if (!array_key_exists('quant' , $servicos[$s])) {
            $servicos[$s]['quant'] = 0;
         } elseif ($servicos[$s]['quant'] == 0) {
            $servicos[$s]['quant'] = 1;
        } else {   
            $pos = strpos($servicos[$s]['quant'], ':');
            
            if ($pos === false) {
            } else {
                $arrval = explode(':',$servicos[$s]['quant']);
                $hh = $arrval[0] * 3600;
                $mm = $arrval[1] * 60;
                $hs = (($hh + $mm) / 60) / 60;
                
                $servicos[$s]['quant'] = $hs;
            }

         }
         $servicos[$s]['quant'] = str_replace(',', '.', $servicos[$s]['quant']);

         if (!array_key_exists('valor' , $servicos[$s])) {
            $servicos[$s]['valor'] = 0;
         } elseif ($servicos[$s]['valor'] == 0) {
            if (array_key_exists('valtot' , $servicos[$s])) {
               $servicos[$s]['valor'] = $servicos[$s]['valtot'];
            }
         }

         $servicos[$s]['valor'] = str_replace(',', '.', $servicos[$s]['valor']);

         
         ## Move peças da tabela de serviços para tabela de peças...
         if (array_key_exists('tipser' , $servicos[$s])) {
            if ($servicos[$s]['tipser'] == 2) {
               $rowp = change_key($servicos[$s], 'codser', 'codpec');
               $os['pecas'][] = $rowp;
               //unset($servicos[$s]);
            } else {
               $ser[$s]['codser'] = $servicos[$s]['codser'];
               $ser[$s]['descri'] = $servicos[$s]['descri'];
               $ser[$s]['quant']  = $servicos[$s]['quant'];
               $ser[$s]['valor']  = $servicos[$s]['valor'];
            }
         } else {
            $ser[$s]['codser'] = $servicos[$s]['codser'];
            $ser[$s]['descri'] = $servicos[$s]['descri'];
            $ser[$s]['quant']  = $servicos[$s]['quant'];
            $ser[$s]['valor']  = $servicos[$s]['valor'];
         }

      }

      $pjsonSer = retornaJson('ttfcspv', $pidipas, $ser);

      ## Pecas
      $pecas = $os['pecas'];

      $pec = array();
      for ($p = 0; $p < count($pecas); $p++) {
         if (!array_key_exists('codpec', $pecas[$p])) $pecas[$p]['codpec'] = '';
         if (!array_key_exists('descri', $pecas[$p])) $pecas[$p]['quant']  = '';

         if (!array_key_exists('quant' , $pecas[$p])) {
            $pecas[$p]['quant'] = 0;
         } elseif ($pecas[$p]['quant'] == 0) {
            $pecas[$p]['quant'] = 1;
         }

         if (!array_key_exists('valor' , $pecas[$p])) $pecas[$p]['valor']  = 0;

         $pec[$p]['codpec'] = $pecas[$p]['codpec'];
         $pec[$p]['descri'] = $pecas[$p]['descri'];
         $pec[$p]['quant']  = $pecas[$p]['quant'];
         $pec[$p]['valor']  = $pecas[$p]['valor'];
      }
      $pjsonPec = retornaJson('ttfcppv', $pidipas, $pec);
      //salvaLog(array($pjsonPec));

      ## Cliente
      //salvaLog(array('01=> ' . print_r($os['cliente'],true)));
      if (!array_key_exists('cliente', $os)) {
         $os['cliente'] = array();
         $os['cliente'][0]['cgccpf'] = (array_key_exists('cgccpf', $os) ? $os['cgccpf'] : '');
         $os['cliente'][0]['nome']   = (array_key_exists('nome'  , $os) ? $os['nome']   : '');
         $os['cliente'][0]['endere'] = (array_key_exists('endere', $os) ? $os['endere'] : '');
         $os['cliente'][0]['endnum'] = (array_key_exists('endnum', $os) ? $os['endnum'] : '');
         $os['cliente'][0]['cidade'] = (array_key_exists('cidade', $os) ? $os['cidade'] : '');
         $os['cliente'][0]['bairro'] = (array_key_exists('bairro', $os) ? $os['bairro'] : '');
         $os['cliente'][0]['uf']     = (array_key_exists('uf'    , $os) ? $os['uf']     : '');
         $os['cliente'][0]['cep']    = (array_key_exists('cep'   , $os) ? $os['cep']    : '');
         $os['cliente'][0]['comp']   = (array_key_exists('comp'  , $os) ? $os['comp']   : '');
         $os['cliente'][0]['fone']   = (array_key_exists('fone'  , $os) ? $os['fone']   : '');
         $os['cliente'][0]['iestad'] = (array_key_exists('iestad', $os) ? $os['iestad'] : '');
         $os['cliente'][0]['e_mail'] = (array_key_exists('e_mail', $os) ? $os['e_mail'] : '');
      }

      if (!array_key_exists('cgccpf', $os['cliente'][0])) { 
         if (array_key_exists('tipcli', $os['cliente'][0])) { 
            $os['cliente'][0]['cgccpf'] = ($os['cliente'][0]['tipcli'] == 'J' ? 
                              $os['cliente'][0]['cgc'] : $os['cliente'][0]['cpf']);
            $ordem[0]['cgccpf'] = $os['cliente'][0]['cgccpf'];
         } elseif (array_key_exists('cgccpf', $ordem[0])) { 
            $os['cliente'][0]['cgccpf'] = $ordem[0]['cgccpf'];
         } else {
            $os['cliente'][0]['cgccpf'] = '';
         }
      } else {
         if (array_key_exists('tipcli', $os['cliente'][0])) {
            $os['cliente'][0]['cgccpf'] = ($os['cliente'][0]['tipcli'] == 'J' ? 
                              $os['cliente'][0]['cgc'] : $os['cliente'][0]['cpf']);
            $ordem[0]['cgccpf'] = $os['cliente'][0]['cgccpf'];

         } else {
            $ordem[0]['cgccpf'] = '';
         }
      }

      if (!array_key_exists('nome'  , $os['cliente'][0])) $os['cliente'][0]['nome']   = '';
      if (!array_key_exists('endere', $os['cliente'][0])) $os['cliente'][0]['endere'] = '';
      if (!array_key_exists('endnum', $os['cliente'][0])) $os['cliente'][0]['endnum'] = '';
      if (!array_key_exists('cidade', $os['cliente'][0])) $os['cliente'][0]['cidade'] = '';
      if (!array_key_exists('bairro', $os['cliente'][0])) $os['cliente'][0]['bairro'] = '';
      if (!array_key_exists('uf'    , $os['cliente'][0])) $os['cliente'][0]['uf']     = '';
      if (!array_key_exists('cep'   , $os['cliente'][0])) $os['cliente'][0]['cep']    = '';
      if (!array_key_exists('comp'  , $os['cliente'][0])) $os['cliente'][0]['comp']   = '';
      if (!array_key_exists('fone'  , $os['cliente'][0])) $os['cliente'][0]['fone']   = '';
      
      if (!array_key_exists('iestad', $os['cliente'][0])) { 
         if (array_key_exists('tipcli', $os['cliente'][0])) { 
            $os['cliente'][0]['iestad'] = ($os['cliente'][0]['tipcli'] == 'J' ? 
                              $os['cliente'][0]['ie'] : $os['cliente'][0]['rg']);
         } else {
            $os['cliente'][0]['iestad'] = '';
         }
      } else {
         //salvaLog(array($os['cliente'][0]['iestad']));
      }
      
      if (!array_key_exists('e_mail', $os['cliente'][0])) $os['cliente'][0]['e_mail'] = '';

      $os['cliente'][0]['cgccpf'] = str_replace($aLimpaCampos, '', $os['cliente'][0]['cgccpf']);
      $os['cliente'][0]['cep']    = str_replace($aLimpaCampos, '', $os['cliente'][0]['cep']);

      if ($os['cliente'][0]['cep'] != '' && strlen($os['cliente'][0]['cep']) > 8) {
          $os['cliente'][0]['cep'] = substr($os['cliente'][0]['cep'], 0, 8);
      }

      $cliente = $os['cliente'];
      /*
      $cliente = array('cgccpf' => $os['cgccpf'],
                       'nome'   => $os['nome'],
                       'endere' => $os['endere'],
                       'endnum' => $os['endnum'],
                       'cidade' => $os['cidade'],
                       'bairro' => $os['bairro'],
                       'uf'     => $os['uf'],
                       'cep'    => $os['cep'],
                       'comp'   => $os['comp'],
                       'fone'   => $os['fone'],
                       'iestad' => $os['iestad'],
                       'e_mail' => $os['e_mail']);
      */

      unset($cliente[0]['tipcli']);
      unset($cliente[0]['cgc']);
      unset($cliente[0]['cpf']);
      unset($cliente[0]['ie']);
      unset($cliente[0]['rg']);

      //salvaLog(array('99=> ' . print_r($cliente,true)));

      $pjsonCli = retornaJson('ttfcusu', $pidipas, $cliente);

      ## Veiculo
      if (!array_key_exists('veiculo', $os)) {
         $os['veiculo'] = array();
         $os['veiculo'][0]['placa']  = (array_key_exists('placa' , $os) ? $os['placa']  : '');
         $os['veiculo'][0]['chassi'] = (array_key_exists('chassi', $os) ? $os['chassi'] : '');
         $os['veiculo'][0]['marca']  = (array_key_exists('marca' , $os) ? $os['marca']  : '');
         $os['veiculo'][0]['modelo'] = (array_key_exists('modelo', $os) ? $os['modelo'] : '');
         $os['veiculo'][0]['versao'] = (array_key_exists('versao', $os) ? $os['versao'] : '');
         $os['veiculo'][0]['anomod'] = (array_key_exists('anomod', $os) ? $os['anomod'] : '');
         $os['veiculo'][0]['anofab'] = (array_key_exists('anofab', $os) ? $os['anofab'] : '');
         $os['veiculo'][0]['corvei'] = (array_key_exists('corvei', $os) ? $os['corvei'] : '');
         $os['veiculo'][0]['cmbvei'] = (array_key_exists('cmbvei', $os) ? $os['cmbvei'] : '');
         $os['veiculo'][0]['km']     = (array_key_exists('km'    , $os) ? $os['km']     : '');
         $os['veiculo'][0]['codcli'] = (array_key_exists('codcli', $os) ? $os['codcli'] : '');
         $os['veiculo'][0]['descri'] = (array_key_exists('descri', $os) ? $os['descri'] : '');
      }

      if (!array_key_exists('placa' , $os['veiculo'][0])) $os['veiculo'][0]['placa']  = '';
      if (!array_key_exists('chassi', $os['veiculo'][0])) $os['veiculo'][0]['chassi'] = '';
      if (!array_key_exists('marca' , $os['veiculo'][0])) $os['veiculo'][0]['marca']  = '';
      if (!array_key_exists('modelo', $os['veiculo'][0])) $os['veiculo'][0]['modelo'] = '';
      if (!array_key_exists('versao', $os['veiculo'][0])) $os['veiculo'][0]['versao'] = '';
      if (!array_key_exists('anomod', $os['veiculo'][0])) $os['veiculo'][0]['anomod'] = '';
      if (!array_key_exists('anofab', $os['veiculo'][0])) $os['veiculo'][0]['anofab'] = '';
      if (!array_key_exists('corvei', $os['veiculo'][0])) $os['veiculo'][0]['corvei'] = '';
      if (!array_key_exists('cmbvei', $os['veiculo'][0])) $os['veiculo'][0]['cmbvei'] = '';
      if (!array_key_exists('km'    , $os['veiculo'][0])) $os['veiculo'][0]['km']     = '';
      if (!array_key_exists('codcli', $os['veiculo'][0])) $os['veiculo'][0]['codcli'] = '';
      if (!array_key_exists('descri', $os['veiculo'][0])) $os['veiculo'][0]['descri'] = '';


      $os['veiculo'][0]['placa'] = str_replace($aLimpaCampos, '', $os['veiculo'][0]['placa']);

      $veiculo = $os['veiculo'];
      unset($veiculo[0]['codcli']);
      $pjsonVei = retornaJson('ttfcvei', $pidipas, $veiculo);

      ## Depurar Navegador
      $mostra = false;
      if (PHP_SAPI != 'cli' and $mostra == true) {
         echo "<br/>";
         echo '[pservico] => ' . $pservico . "<br/>";
         echo '[pmetodo]  => ' . $pmetodo  . "<br/>";
         echo '[pcodemp]  => ' . $pcodemp  . "<br/>";
         echo '[pidipas]  => ' . $pidipas  . "<br/>";
         echo '[pjsonPas] => ' . $pjsonPas . "<br/>";
         echo '[pjsonRel] => ' . $pjsonRel . "<br/>";
         echo '[pjsonSer] => ' . $pjsonSer . "<br/>";
         echo '[pjsonPec] => ' . $pjsonPec . "<br/>";
         echo '[pjsonCli] => ' . $pjsonCli . "<br/>";
         echo '[pjsonVei] => ' . $pjsonVei . "<br/>";
         echo "<br/>";
      }

      if ($saida != 'Texto') {
         ## URL para depuracao
         $url_str = $url
               . '?pservico=' . $pservico
               . '&pmetodo='  . $pmetodo
               . '&pcodemp='  . $pcodemp
               . '&pidipas='  . $pidipas
               . '&pjsonPas=' . $pjsonPas
               . '&pjsonRel=' . $pjsonRel
               . '&pjsonSer=' . $pjsonSer
               . '&pjsonPec=' . $pjsonPec
               . '&pjsonCli=' . $pjsonCli
               . '&pjsonVei=' . $pjsonVei;

         ## Parametros da funcao de sincronizacao
         $parametros = array('pservico' => $pservico,
                             'pmetodo'  => $pmetodo,
                             'pcodemp'  => $pcodemp,
                             'pidipas'  => $pidipas,
                             'pjsonPas' => $pjsonPas,
                             'pjsonRel' => $pjsonRel,
                             'pjsonSer' => $pjsonSer,
                             'pjsonPec' => $pjsonPec,
                             'pjsonCli' => $pjsonCli,
                             'pjsonVei' => $pjsonVei
                            );

         ## Chamada da funcao
         $salvaRet = '';
         $salvaRet = enviaDados($url, $parametros);

         $mensagens = array();
         $mensagens[] = 'Psg: ' . $pidipas
                      . ' de ' . $ordem[0]['dtpsg']
                      . ' Placa ' . $veiculo[0]['placa']
                      . ' enviado.';
         //$mensagens[] = $url_str;

         if (SALVA_LOGS == "true") {
            salvaLog($mensagens);
         }

         //exit;

         $Atual  = date('H:i:s');
         $t_pass = round(abs(strtotime($Inicio) - strtotime($Atual)) / 60, 2);

         if (PHP_SAPI == 'cli') {
            //echo "[".$Atual."/".($t_pass * 60)."/".$tempo."] " . $mensagens[0] . PHP_EOL;
         }
      } else { // if ($saida != 'Texto')
         salvaTxt('CAB;' . $pcodemp . ';'. $pidipas . ';');

         if (!empty($pjsonPas))
            geraLinha('ttfccpv', 'PAS', objectToArray(json_decode($pjsonPas)));
         if (!empty($pjsonRel))
            geraLinha('ttfcrpv', 'REL', objectToArray(json_decode($pjsonRel)));
         if (!empty($pjsonSer))
            geraLinha('ttfcspv', 'SER', objectToArray(json_decode($pjsonSer)));
         if (!empty($pjsonPec))
            geraLinha('ttfcppv', 'PEC', objectToArray(json_decode($pjsonPec)));
         if (!empty($pjsonCli))
            geraLinha('ttfcusu', 'CLI', objectToArray(json_decode($pjsonCli)));
         if (!empty($pjsonVei))
            geraLinha('ttfcvei', 'VEI', objectToArray(json_decode($pjsonVei)));
      }

      $Extraidos++;

      //if ($i >= (count($serv) -1)) {
      if ($i >= count($serv)) {
         //$Fim = 1;
         break;
      }

      //print_r($pjsonPec);

      //exit;

   }

   //exit;

   ## Tempo em espera para a proxima execucao
   if ($saida !== 'Texto') {
      $serv = NULL;

      if (($pidipas >= $ultpsg_geral) || ($pidipas === "") ) {

         if ($pidipas !== "") {
            salvaLog(array('Final - OS Atual: ' . ($pidipas == "" ? $ultpsg : $pidipas)
                        . ' Ultima OS: ' . $ultpsg 
                        . ' : ' . $Extraidos 
                        . ' Extraidos...'));
            
            salvaLog(array('(0) Ultima OS: ' . $pidipas . ' <=> ' . $ultpsg_geral));
         
            if($pidipas == $ultpsg_geral || $pidipas === "") {
               $Fim = 1;
               salvaLog(array('(1) Ultima OS: ' . $ultpsg_geral));
               exit;
            }
         } else {
            $Fim = 1;
            salvaLog(array('(2) Ultima OS: ' . $ultpsg . '/' . $ultpsg_geral));
            exit;
         }
      }

      if ((($t_pass * 60) < $tempo) && $Fim <= 0) {
         $t = $tempo - ($t_pass * 60);

         sleep($t);

         //$Inicio = date('H:i:s');
         $mensagens = array($Inicio . ' - '
                          . $Atual  . ' - '
                          . $t_pass . ' - '
                          . $tempo  . ' - '
                          . ($t_pass * 60)) . ' - '
                          . $ultpsg  . ' - '
                          . $ultpsg_geral;
         salvaLog($mensagens);
      }
   } else {

      $serv = NULL;

      if (($pidipas >= $ultpsg_geral) || ($pidipas == "") ) {

         if ($pidipas !== "") {
            //salvaLog(array('Final - OS Atual: ' . $pidipas 
            //            . ' Ultima OS: ' . $ultpsg 
            //            . ' : ' . $Extraidos 
            //            . ' Extraidos...'));
            if($pidipas == $ultpsg_geral) {
               $Fim = 1;
               salvaLog(array('(3) Ultima OS: ' . $ultpsg_geral));
               exit;
            }
         } else {
            $Fim = 1;
            salvaLog(array('(4) Ultima OS: ' . $ultpsg));
            exit;
         }
      }

      salvaLog(array('OS Atual: ' . $pidipas 
                   . ' - ' . $Extraidos 
                   . ' Extraidos...'));

   }

   $conn = null;
   odbc_close_all();

   //exit;

} while ($Fim <= 0);
## Final do Temporizador ##

$Conn = null;
$conn = null;
odbc_close_all();

exit(0);

############################################################################
function geraLinha($tabela, $tipo, $aPas) {
############################################################################
   $tPas = '';
   for ($x = 0; $x < count($aPas[$tabela]); $x++) {
      $tPas = $tipo.';';
      if ($tipo == 'CLI') {
         //salvaLog(array(print_r($aPas[$tabela][$x],true)));
         $tPas .= $aPas[$tabela][$x]['idipas'] . ';';
         $tPas .= $aPas[$tabela][$x]['cgccpf'] . ';';
         $tPas .= $aPas[$tabela][$x]['nome'] . ';';
         $tPas .= $aPas[$tabela][$x]['endere'] . ';';
         $tPas .= $aPas[$tabela][$x]['endnum'] . ';';
         $tPas .= $aPas[$tabela][$x]['bairro'] . ';';
         $tPas .= $aPas[$tabela][$x]['cidade'] . ';';
         $tPas .= $aPas[$tabela][$x]['uf'] . ';';
         $tPas .= $aPas[$tabela][$x]['cep'] . ';';
         $tPas .= $aPas[$tabela][$x]['comp'] . ';';
         $tPas .= $aPas[$tabela][$x]['fone'] . ';';
         $tPas .= $aPas[$tabela][$x]['iestad'] . ';';
         $tPas .= $aPas[$tabela][$x]['e_mail'] . ';';
      } else {
         foreach($aPas[$tabela][$x] as $campo => $valor) {
            $tPas .= $valor.';';
         }
      }
      salvaTxt($tPas);
   }
   return $tPas;
}

############################################################################
function salvaTxt($texto = '') {
############################################################################
   if (!empty($texto)) {
      $Salva = fopen(TXT_PATH.TXT_FILE, 'a+');
      fwrite($Salva, $texto . PHP_EOL);
      fclose($Salva);
   }
}

############################################################################
function salvaLog($mensagens = array()) {
############################################################################
   for ($i=0;$i<count($mensagens);$i++) {
      $Salva = fopen(LOG_PATH.LOG_FILE, 'a+');
      //fwrite($Salva, "[".date("H:i:s")."] ".str_repeat("#",80)."\n");
      fwrite($Salva, "[".date("H:i:s")."] " . $mensagens[$i] . PHP_EOL);
      fclose($Salva);

      ## Mostra na tela do client
      if (PHP_SAPI == 'cli') {
         echo "[".date("H:i:s")."] " . $mensagens[$i] . PHP_EOL;
      } else {
         echo "[".date("H:i:s")."] " . $mensagens[$i] . '<br/>';
      }
   }
}

############################################################################
function enviaDados($url, $parametros = array()) {
############################################################################

   $ch = curl_init();

   curl_setopt_array($ch, array(
       CURLOPT_RETURNTRANSFER => 1,
       CURLOPT_URL            => $url,
       CURLOPT_ENCODING       => "gzip, deflate",
       CURLOPT_POST           => 1,
       CURLOPT_POSTFIELDS     => $parametros
   ));

   ## Autenticacao Proxy
   if (PROXY == "true") {
      $proxy = array(
         CURLOPT_HTTPPROXYTUNNEL => 1,
         CURLOPT_PROXYTYPE       => "CURLPROXY_HTTP",
         CURLOPT_PROXY           => SERVIDOR.':'.PORTA,
         CURLOPT_PROXYUSERPWD    => USUARIO.':'.SENHA,
         CURLOPT_PROXYAUTH       => "CURLAUTH_BASIC"
      );
      curl_setopt_array($ch, $proxy);
   }

   $response = curl_exec($ch);

   if ($response === false) {
      $result = 'Erro Curl: ' . curl_error($ch);
      curl_close($ch);
   } else {
      curl_close($ch);

      if ($response == "") {
         $result = '<retorno>Ocorreu um erro na comunicacao com o servidor!</retorno>';
      } else {
         if (is_object($response) || substr($response, 0, 1) == '{') {
            $json = json_decode($response);

            if ($parametros['pservico'] != 'wfcusu' && $parametros['pservico'] != 'wfcpas') {
               $arr = objectToArray($json);

               $xml_data = new SimpleXMLElement('<GravarResult/>');
               array_to_xml($arr, $xml_data);
               $result = str_replace('<?xml version="1.0"?>','',$xml_data->asXML());
               $result = str_replace('<GravarResult>','',$result);
               $result = str_replace('</GravarResult>','',$result);
               $result = str_replace('<ProDataSet>','',$result);
               $result = str_replace('</ProDataSet>','',$result);
               $result = str_replace('<item0>','',$result);
               $result = str_replace('</item0>','',$result);
            } else {
               $result = $json;
            }
         } else {
            if (substr($response, 0, 1) == '<') {

               $resp = $response;
               $resp = str_replace('<?xml version="1.0"?>','',$resp);
               $resp = str_replace('<ProDataSet xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">','',$resp);
               $resp = str_replace('</ProDataSet>','',$resp);

               $result = simplexml_load_string($resp);
            } else {
               $result = $response;
            }
         }
      }
   }
   return $result;
}

############################################################################
function Conecta($Type, $Host, $Data, $User=NULL, $Pass=NULL, $Port=NULL) {
############################################################################
   /*
   //--> MySQL
   $Conn = new PDO("mysql:host=hostname; dbname=nome_banco;", USUARIO, SENHA, $array_opcoes);

   //--> PostgreSQL
   $Conn = new PDO("pgsql:host=hostname; dbname=nome_banco;", USUARIO, SENHA, $array_opcoes);

   //--> SQL Server
   // DSN PDO para Windows
   $Conn = new PDO("sqlsrv:server=hostname; database=nome_banco;", USUARIO, SENHA, $array_opcoes);

   // DSN PDO para Linux
   $Conn = new PDO("dblib:server=hostname; database=nome_banco;", USUARIO, SENHA, $array_opcoes);

   //--> SQLite
   // DSN PDO para trabalhar com banco SQLite em memória
   $Conn = new PDO('sqlite::memory', null, null, $array_opcoes);

   // DSN PDO para trabalhar com banco SQLite em disco
   $Conn = new PDO("sqlite:caminho_fisco_banco", USUARIO, SENHA, $array_opcoes);

   //--> Oracle
   $host = 'localhost';
   $port = '1521';
   $user = 'root';
   $pass = 'root';
   $Conn = new PDO("oci:dbname=//$host:$port",$user,$pass);

   //--> Firebird
   $Conn = new PDO("firebird:host=hostname; dbname=nome_banco;", USUARIO, SENHA, $array_opcoes);

   //--> Exemplo
   try {
   	$Conn = Conecta("Firebird", "localhost", "D:\Clientes\FichaDoCarro\Zavati\ZAVATI-OFICINA.FDB");
   } catch (Exception $e) {
   	echo "Erro na Conexao: ", htmlentities($e->getMessage());
   	die();
   }

   unset($Conn); //--> encerrando a conexão
   */
   
   switch (strtolower($Type)) {
      case 'firebird':
         $DSN = "firebird:dbname=$Host:$Data";
         if ($User == NULL) $User = "sysdba";
         if ($Pass == NULL) $Pass = "masterkey";
      break;
   }
   
   $options  = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);

   try {
   	$pdo = new PDO($DSN, $User, $Pass, $options);
   } catch (Exception $e) {
   	$pdo = NULL;
   	$erro = "Erro na Conexao: " . htmlentities($e->getMessage());
   	die();
   }

   return $pdo;
}

############################################################################
function Consulta($Conn, $Sql, $Id=NULL, $Type=NULL) {
############################################################################
   try {
      $Result = $Conn->prepare($Sql);
      $Result->execute();
   } catch (Exception $e) {
      salvaLog(array("Erro ao tentar conectar PDO (Ser): " . htmlentities($e->getMessage())));
      exit;
   }

   switch (strtolower($Type)) {
      case 'i': //--> Retorna Array Indices
         $Return = $Result->fetchAll(PDO::FETCH_NUM); 
         break;
      case 'b': //--> Retorna Array Nomes e Indices
         $Return = $Result->fetchAll(PDO::FETCH_BOTH); 
         break;
      case 'j': //--> Retorna StdClass
         $Return = $Result->fetchAll(PDO::FETCH_OBJ); 
         break;
      default:  //--> Retorna Array Nomes
         $Return = $Result->fetchAll(PDO::FETCH_ASSOC); 
         break;
   }

   if ($Id===NULL) {
      return $Return;
   } else {
      return $Return[$Id];
   }
}

############################################################################
function sqlret($tabela,$campos=array(),$condicao=array(),$ordem=array()) {
############################################################################
   $maxregs = 250;

   $sql  = "SELECT ";

   switch (TIPO_DB) {
      case 'ACCESS':   $sql .= ""; break;
      case 'DBASE':    $sql .= ""; break;
      case 'FIREBIRD': $sql .= "FIRST " . $maxregs .  " "; break;
      case 'FOXPRO':   $sql .= ""; break;
      default:         $sql .= "";
   }

   $sql .= (count($campos) > 0 ? campos($campos) : "*") . " "
         . "FROM " . $tabela;
   
   if (count($condicao) > 0) {
      $sql .= ' WHERE ';
      if (TIPO_DB == 'ACCESS') {
         $sql .= '(' . condicao($condicao, $tabela) . ')';
      } else {
         $sql .= condicao($condicao, $tabela);
      }
   }
   
   $sql .= (count($ordem) > 0 ? ' ORDER BY ' . ordem($ordem) : '');

   return str_replace("  ", " ", $sql);
}

############################################################################
function campos($arrCampos = array()) {
############################################################################
   if ($arrCampos !== NULL && count($arrCampos) > 0) {
      $aCam = array();
      foreach($arrCampos as $campo => $valor) {
         if ($valor != 'S' && $campo != 'idtipo' && $campo != 'idmask') {
            $n = explode(" ", $valor);
            $aCam[] = count($n) > 1 ? '['.$valor.']' : $valor;
         }
      }
      $sCam = implode(', ', $aCam);
      return $sCam;
   } else {
      return '*';
   }
}

############################################################################
function condicao($arrCond = array(), $tabela = '') {
############################################################################
   if (!empty($arrCond)) {
      $cond_str = '';
      for ($i = 0; $i < count($arrCond); $i++) {
         $t = '';
         $f = '';
         $c = '';
         $o = '';
         $l = '';

         if (TIPO_DB == 'FIREBIRD' || TIPO_DB == 'ACCESS') {
            $cond_str .= "(";
         }
         
         foreach($arrCond[$i] as $campo => $valor) {
            //echo '01 => ' . $campo . ' => ' . $valor . '<br/>';
            if ($campo == 'Tipo') {
               $t = $valor;
               continue;
            }
            if ($campo == 'Funcao') {
               $f = $valor;
               continue;
            }
            if ($campo == 'Campo' && $t == 'F') {
               $c = $valor;
               continue;
            } 
            if ($campo == 'Operador' && $t == 'F') {
               $o = $valor;
               continue;
            }
            if ($campo == 'OpLogico' && $t == 'F') {
               $l = $valor;
            }
            if ($campo == 'Valor') {
               //salvaLog(array('02 => ' . $t . ' => ' . $campo . ' => ' . $valor . ' => ' . get_type_stable($valor)));
               switch (get_type_stable($valor)) {
                  case 'integer':
                     if ($t != 'F') {
                        if ($t == 'S') {
                           $cond_str .= "'" . $valor . "'";
                        } else {
                           $cond_str .= intval($valor);
                        }
                     }
                     break;
                  case 'double':
                     if ($t != 'F') {
                        if ($t == 'S') {
                           $cond_str .= "'" . $valor . "'";
                        } else {
                           $cond_str .= $valor;
                           //salvaLog(array($valor));
                        }
                     }
                     //switch (TIPO_DB) {
                     //   case 'DBASE': $cond_str .= "'" . $valor . "'"; break;
                     //   default: $cond_str .= $valor;
                     //}
                     break;
                  case 'date':
                     //salvaLog(array('03 => ' . $t 
                     //. ' => ' . $campo 
                     //. ' => ' . $valor 
                     //. ' => ' . $t 
                     //. ' => ' . $TIPO_DB 
                     //. ' => ' . date('d/m/Y', strtotime($valor)) 
                     //));

                     switch (TIPO_DB) {
                        case 'ACCESS': $cond_str .= "#".$valor."#"; break;
                        case 'DBASE': $cond_str .= "#".$valor." 00:00:00 AM#"; break;
                        case 'FIREBIRD': $cond_str .= "DATE '".date('d/m/Y', strtotime($valor))."'"; break;
                        case 'FOXPRO': $cond_str .= "{d '".$valor."'}"; break;
                        default: $cond_str .= $valor;
                     }
                     break;
                  default:
                     if ($valor !== 0 && $valor !== "") {
                        //salvaLog(array('03 => ' . $valor));
                        switch ($valor) {
                           case 'var_os':
                           case 'var_codcli':
                           case 'var_serv':
                           case 'var_prod':
                           case 'var_cli':
                              if ($t == 'S') {
                                 $cond_str .= "'%s'";
                              } else {
                                 $cond_str .= '%u';
                              }
                              break;
                           case 'var_cor':
                           case 'var_combustivel':
                           case 'var_marca':
                           case 'var_modelo':
                           case 'var_placa':
                              $cond_str .= "'%s'";
                              break;
                           default:
                              $valor = str_replace("'", '', $valor);
                              $valor = str_replace('"', '', $valor);

                             if (strtoupper($valor) != 'NULL') {
                                 if ($t != 'F') {
                                    if (TIPO_DB == 'DBASE' || TIPO_DB == 'FIREBIRD') {
                                       if ($t != 'I') {
                                          $cond_str .= "'" . utf8_decode($valor) ."' ";
                                       } else {
                                          $cond_str .= intval($valor);
                                       }
                                    } else {
                                       if ($t != 'I') {
                                          if (TIPO_DB == 'ACCESS') {
                                             if ($valor == 'True' || $valor == 'False') {
                                                $cond_str .= utf8_decode($valor);
                                             } else {
                                                $cond_str .= '"' . utf8_decode($valor) .'" ';
                                             }
                                          } else {
                                             $cond_str .= '"' . utf8_decode($valor) .'" ';
                                          }
                                       } else {
                                          $cond_str .= intval($valor);
                                       }
                                    }
                                 } else {
                                    $cond_str .= $f."(" . utf8_decode($c) .") ". $o . " " . $valor . " ";
                                 }
                              } else {
                                 $cond_str .= utf8_decode($valor) ." ";
                              }
                        }
                     } else {
                        $valor = str_replace("'", '', $valor);
                        $valor = str_replace('"', '', $valor);

                        if (strtoupper($valor) != 'NULL') {
                           if ($t != 'F') {
                              if (TIPO_DB == 'DBASE' || TIPO_DB == 'FIREBIRD') {
                                 if ($t != 'I') {
                                    $cond_str .= "'" . utf8_decode($valor) ."' ";
                                 } else {
                                    $cond_str .= intval($valor);
                                 }
                              } else {
                                 if ($t != 'I') {
                                    $cond_str .= '"' . utf8_decode($valor) .'" ';
                                 } else {
                                    $cond_str .= intval($valor);
                                 }
                              }
                           } else {
                              $cond_str .= $f."(" . utf8_decode($c) .") ". $o . " " . $valor . " ";
                           }
                        } else {
                           $cond_str .= utf8_decode($valor) ." ";
                        }
                     }
               }
            } else {
               if ($t != 'F') {
                  if ($campo == 'OpLogico' && (TIPO_DB == 'FIREBIRD' || TIPO_DB == 'ACCESS')) {
                     $cond_str = trim($cond_str) . ') ' . utf8_decode($valor) . ' ';
                  } else {
                     if ($campo == 'Campo' && TIPO_DB == 'ACCESS') {
                        $cond_str .= '(' . $tabela . '.[' . utf8_decode($valor) . ']) ';
                     } else {
                        $cond_str .= utf8_decode($valor) . ' ';
                     }
                  }
               } else {
                  $cond_str .= utf8_decode($valor) . ' ';
               }
            }
            if ($t != 'F') {
               $cond_str .= ' ';
            }

         }
      }

      return str_replace("  ", " ", $cond_str);
   }
}

############################################################################
function ordem($arrCampos = array()) {
############################################################################
   if ($arrCampos !== NULL && count($arrCampos) > 0) {
      $sCam = implode(', ', $arrCampos);
      return utf8_decode($sCam);
   } else {
      return '';
   }
}

############################################################################
function retornaJson($tabela, $pidipas, $arr) {
############################################################################
   if (count($arr) > 0){
      $aFind = array("\n","\r","\t");
      for ($x=0; $x < count($arr); $x++) {
         $tmp[$x]['idipas'] = $pidipas;
         foreach($arr[$x] as $campo => $valor) {
            //hexdump($valor);
            //if ($campo == 'km') {
            //   $valor = str_replace('KM', '', $valor);
            //   $valor = str_replace(':', '', $valor);
            //   $valor = str_replace('.', '', $valor);
            //   $valor = str_replace(' ', '', $valor);
            //}
            $valor = str_replace($aFind, "", $valor);
            $valor = trim(removeAcento($valor));
            $valor = str_replace('+Ã', 'C', $valor);
            $valor = str_replace('Ë', 'O', $valor);
            $valor = str_replace('C-R', 'CAR', $valor);
            $valor = str_replace('E-C', 'EIC', $valor);
            $valor = str_replace('L-M', 'LAM', $valor);
            $valor = str_replace('S-R', 'SAR', $valor);
            $valor = str_replace('T-V', 'TIV', $valor);
            $valor = str_replace('T-T', 'TAT', $valor);
            $valor = str_replace('IGNIÃO', 'IGNICAO', $valor);
            $valor = str_replace('Ã', 'C', $valor);
            $valor = str_replace('+', 'A', $valor);
            $valor = str_replace('KM:', '', $valor);
            //$valor = str_replace('.', '', $valor);
            $valor = str_replace('MEC-NICA', 'MECANICA', $valor);
            $valor = str_replace('HIDR-ULICA', 'HIDRAULICA', $valor);
            $valor = str_replace('RET-FICA', 'RETIFICA', $valor);
            $valor = str_replace('V-LVULA', 'VALVULA', $valor);
            $tmp[$x][$campo] = $valor;
         }
      }

      $arr_tmp = array();
      $arr_tmp[$tabela] = $tmp;
      return json_encode($arr_tmp);
   } else {
      return '';
   }
}

############################################################################
function removeAcento($texto) {
############################################################################
   $txt = $texto;

   //$txt = mb_convert_encoding($txt, "ASCII", "UTF-8");
   //$txt = iconv('UTF-8','ASCII//IGNORE',$txt);
   //$txt = preg_replace('/[~\'`^]/', null, $txt);
   
   //$txt = mb_convert_encoding($txt, "HTML-ENTITIES", "UTF-8");
   //$txt = html_entity_decode($txt);

   $txt = mungString($txt,'U');

   $txt = strtr($txt, "áàâãäéèêëíìîïóòôõöúùûüç&", "aaaaaeeeeiiiiooooouuuuce");
   $txt = strtr($txt, "ÁÀÂÃÄÉÈÊËÍÌÎÏÓÒÔÕÖÚÙÛÜÇ&", "AAAAAEEEEIIIIOOOOOUUUUCE");

   $encoding = mb_detect_encoding($txt, ENCLIST);
   if ($encoding != 'UTF-8') {
      $txt = mb_convert_encoding($txt, 'UTF-8', $encoding);
   }

   //$txt = strtr($txt, "áàâãäéèêëíìîïóòôõöúùûüç&", "aaaaaeeeeiiiiooooouuuuce");
   //$txt = strtr($txt, "ÁÀÂÃÄÉÈÊËÍÌÎÏÓÒÔÕÖÚÙÛÜÇ&", "AAAAAEEEEIIIIOOOOOUUUUCE");
   $txt = strtoupper($txt);
   return $txt;
}

############################################################################
function hexdump($str, $br=PHP_EOL) {
############################################################################
   if (empty($str)) return FALSE;

   // GET THE HEX BYTE VALUES IN A STRING
   $hex = str_split(implode(NULL, unpack('H*', $str)));

   // ALLOCATE BYTES INTO HI AND LO NIBBLES
   $hi  = NULL;
   $lo  = NULL;
   $mod = 0;
   foreach ($hex as $nib) {
      $mod++;
      $mod = $mod % 2;
      if ($mod) {
          $hi .= $nib;
      } else {
          $lo .= $nib;
      }
   }

   // SHOW THE SCALE, THE STRING AND THE HEX
   $num = substr('1...5...10...15...20...25...30...35...40...45...50...55...60...65...70...75...80...85...90...95..100..105..110..115..120..125..130', 0, strlen($str));

   if ($str != '') {
      salvaLog(array($num));
      salvaLog(array($str));
      if ($hi != '') salvaLog(array('' . $hi));
      if ($lo != '') salvaLog(array('' . $lo));
   }
}

############################################################################
function mungString($str, $return='TEXT') {
############################################################################
    // OUR REPLACEMENT ARRAY OF ENTITIES
    static $entity = array();

    // OUR REPLACEMENT ARRAY OF UTF-8 CHARACERS
    static $utf8 = array();

    // OUR REPLACEMENT ARRAY OF CHARACTERS (YOU MAY WANT SOME CHANGES HERE)
    static $normal = array
    ( 'ƒ' => 'f'  // http://en.wikipedia.org/wiki/%C6%91 florin
    , 'Š' => 'S'  // http://en.wikipedia.org/wiki/%C5%A0 S-caron (voiceless postalveolar fricative)
    , 'š' => 's'  // http://en.wikipedia.org/wiki/%C5%A0 s-caron
    , 'Ð' => 'Dh' // http://en.wikipedia.org/wiki/Eth (voiced dental fricative)
    , 'Ž' => 'Z'  // http://en.wikipedia.org/wiki/%C5%BD Z-caron (voiced postalveolar fricative)
    , 'ž' => 'z'  // http://en.wikipedia.org/wiki/%C5%BD z-caron
    , 'À' => 'A'
    , 'Á' => 'A'
    , 'Â' => 'A'
    , 'Ã' => 'A'
    , 'Ä' => 'A'
    , 'Å' => 'A'
    , 'Æ' => 'E'
    , 'Ç' => 'C'
    , 'È' => 'E'
    , 'É' => 'E'
    , 'Ê' => 'E'
    , 'Ë' => 'E'
    , 'Ì' => 'I'
    , 'Í' => 'I'
    , 'Î' => 'I'
    , 'Ï' => 'I'
    , 'Ñ' => 'N'
    , 'Ò' => 'O'
    , 'Ó' => 'O'
    , 'Ô' => 'O'
    , 'Õ' => 'O'
    , 'Ö' => 'O'
    , 'Ø' => 'O'
    , 'Ù' => 'U'
    , 'Ú' => 'U'
    , 'Û' => 'U'
    , 'Ü' => 'U'
    , 'Ý' => 'Y'
    , 'Þ' => 'Th' // http://en.wikipedia.org/wiki/Thorn_%28letter%29 (Capital Thorn is smaller)
    , 'ß' => 'Ss'
    , 'à' => 'a'
    , 'á' => 'a'
    , 'â' => 'a'
    , 'ã' => 'a'
    , 'ä' => 'a'
    , 'å' => 'a'
    , 'æ' => 'e'
    , 'ç' => 'c'
    , 'è' => 'e'
    , 'é' => 'e'
    , 'ê' => 'e'
    , 'ë' => 'e'
    , 'ì' => 'i'
    , 'í' => 'i'
    , 'î' => 'i'
    , 'ï' => 'i'
    , 'ð' => 'dh'  // http://en.wikipedia.org/wiki/Eth
    , 'ñ' => 'n'
    , 'ò' => 'o'
    , 'ó' => 'o'
    , 'ô' => 'o'
    , 'õ' => 'o'
    , 'ö' => 'o'
    , 'ø' => 'o'
    , 'ù' => 'u'
    , 'ú' => 'u'
    , 'û' => 'u'
    , 'ý' => 'y'
    , 'ý' => 'y'
    , 'þ' => 'th' // http://en.wikipedia.org/wiki/Thorn_%28letter%29
    , 'ÿ' => 'y'
    )
    ;

    // THE EXPECTED RETURN
    $r = strtoupper(substr($return,0,1));

    // RETURN THE "TRANSLATED" TEXT
    if ($r == 'T') return strtr($str, $normal);

    // RETURN THE "ENTITIZED" TEXT
    if ($r == 'E')
    {
        if (empty($entity))
        {
            foreach ($normal as $key => $nothing)
            {
                $entity[$key] = '&#' . ord($key) . ';';
            }
        }
        return strtr($str, $entity);
    }

    // RETURN THE UTF-8 TEXT
    if ($r == 'U')
    {
        if (empty($utf8))
        {
            foreach ($normal as $key => $nothing)
            {
                $utf8[$key] = utf8_encode($key);
            }
        }
        return strtr($str, $utf8);
    }

    // MIGHT BE USEFUL TO GET THE LIST OF ORIGINAL LETTERS
    return array_keys($normal);
}

############################################################################
function get_type_stable($var) {
############################################################################
   $type_ = gettype($var);

   //$mensagens = array('$var: ' . $var . ' $type_: ' . $type_);
   //salvaLog($mensagens);

   if ($type_ == 'unknown type' || $type_ == 'string') {
      $types = array('array','integer','double','date','string','object','resource','null');
      foreach($types as $type) {
         $typechecker = 'is_'.$type;
         if($typechecker($var)) {

            //$mensagens = array('01=> $var: ' . $var . ' $type: ' . $type . ' strlen: ' . strlen($var));
            //salvaLog($mensagens);

            if ((strlen($var) > 6 && $type == 'date') || $type != 'date' ) {
               //$mensagens = array('02=> $var: ' . $var . ' $type: ' . $type . ' strlen: ' . strlen($var));
               //salvaLog($mensagens);
               return $type;
            }
         }
      }
   } else {
      return $type;
   }

}

############################################################################
function Mask($mask, $str) {
############################################################################
   $str = str_replace(" ","",$str);
   for($i=0;$i<strlen($str);$i++){
      $mask[strpos($mask,"#")] = $str[$i];
   }
   return $mask;
}

############################################################################
function is_date($value) {
############################################################################
    if (!$value) {
        return false;
    }

    try {
        new \DateTime($value);
        return true;
    } catch (\Exception $e) {
        return false;
    }
}

############################################################################
function validateDate($date, $format = 'Y-m-d H:i:s') {
############################################################################
   // validateDate('28/02/2012', 'd/m/Y')
   $d = DateTime::createFromFormat($format, $date);
   return $d && $d->format($format) == $date;
}

############################################################################
function change_key($array, $old_key, $new_key) {
############################################################################
   if (!array_key_exists($old_key, $array)) {
      $old_key = strtolower($old_key);
      if (!array_key_exists($old_key, $array)) {
         return $array;
      }
   }

   $keys = array_keys($array);
   $keys[array_search($old_key,$keys)] = $new_key;
   //echo '=>   Encontrei ' . $old_key . ' | ' . $new_key . '<br/>';
   return array_combine($keys, $array);
}

############################################################################
function clearXmlString($string, $removeEncodingTag = false) {
############################################################################
   $aFind = array(
       'xmlns:default="http://www.w3.org/2000/09/xmldsig#"',
       ' standalone="no"',
       'default:',
       ':default',
       "\n",
       "\r",
       "\t"
   );
   $retXml = str_replace($aFind, "", $string);
   $retXml = preg_replace('/(\>)\s*(\<)/m', '$1$2', $retXml);
   if ($removeEncodingTag) {
      $retXml = deleteAllBetween($retXml, '<?xml', '?>');
   }
   return $retXml;
}

############################################################################
function deleteAllBetween($string, $beginning, $end) {
############################################################################
   $beginningPos = strpos($string, $beginning);
   $endPos = strpos($string, $end);
   if ($beginningPos === false || $endPos === false) {
      return $string;
   }
   $textToDelete = substr($string, $beginningPos, ($endPos + strlen($end)) - $beginningPos);
   return str_replace($textToDelete, '', $string);
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

############################################################################
function json_validate($string) {
############################################################################
   // decode the JSON data
   $result = json_decode($string);

   // switch and check possible JSON errors
   switch (json_last_error()) {
      case JSON_ERROR_NONE:
         $error = ''; // JSON is valid // No error has occurred
         break;
      case JSON_ERROR_DEPTH:
         $error = 'A profundidade máxima da pilha foi excedida.';
         break;
      case JSON_ERROR_STATE_MISMATCH:
         $error = 'JSON inválido ou malformado.';
         break;
      case JSON_ERROR_CTRL_CHAR:
         $error = 'Erro de caractere de controle, possivelmente codificado incorretamente.';
         break;
      case JSON_ERROR_SYNTAX:
         $error = 'Erro de sintaxe, JSON malformado.';
         break;
      // PHP >= 5.3.3
      case JSON_ERROR_UTF8:
         $error = 'Caracteres UTF-8 malformados, possivelmente codificados incorretamente.';
         break;
      // PHP >= 5.5.0
      case JSON_ERROR_RECURSION:
         $error = 'Uma ou mais referências recursivas no valor a ser codificado.';
         break;
      // PHP >= 5.5.0
      case JSON_ERROR_INF_OR_NAN:
         $error = 'Um ou mais valores NAN ou INF no valor a ser codificado.';
         break;
      case JSON_ERROR_UNSUPPORTED_TYPE:
         $error = 'Um valor de um tipo que não pode ser codificado foi fornecido.';
         break;
      default:
         $error = 'Ocorreu um erro JSON desconhecido.';
         break;
   }

   if ($error !== '') {
      // throw the Exception or exit // or whatever :)
      return $error;
   }

   // everything is OK
   return $result;
}


?>
