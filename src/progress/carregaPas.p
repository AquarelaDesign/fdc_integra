/*******************************************************************************
 * Sistema.......: Ficha do Carro                                              *
 * Programa......: carregaPas.p                                                *
 * Titulo........: Carrega arquivo texto com dados de Passagens de Veiculos    *
 *                 da Oficina para as tabelas                                  *
 * Responsavel...: Jose Augusto Freire                                         *
 * Data..........: 13/08/2018                                                  *
 *                                                                             *
 * O arquivo a ser importado devera seguir os seguintes padroes:               *
 *  - Estar em formato de codificacao UTF-8                                    *
 *  - Os Campos devem estar separados por ';' ponto-e-virgula                  *
 *  - Datas formatadas como DD/MM/AAAA                                         *
 *  - Numero formatado sem separadores de milhar e com '.' ponto para o        *
 *    separador de milhar                                                      *
 *                                                                             *
 *+----+---------------------+-------+----+-+---------------------------------+*
 *|                  L A Y O U T   D O   A R Q U I V O                        |*
 *+----+---------------------+-------+----+-+---------------------------------+*
 *|Tipo|Campo                |Valor  |Tam.|O|Descricao                        |*
 *+----+---------------------+-------+----+-+---------------------------------+*
 *|CAB |Email_da_Empresa     |String | 100|X|Email/Login da Empresa           |*
 *|CAB |Numero_da_Passagem   |String |  20|X|ID da Passagem/Ordem de Servico  |*
 *+----+---------------------+-------+----+-+---------------------------------+*
 *|PAS |Numero_da_Passagem   |String |  20|X|ID da Passagem/Ordem de Servico  |*
 *|PAS |Placa                |String |   7|X|Placa                            |*
 *|PAS |Chassi               |String |  17| |Chassi do Veiculo                |*
 *|PAS |KM                   |Integer|   9| |KM do Veiculo na Abertura        |*
 *|PAS |Data_da_Passagem     |Date   |   7|X|Data da Abertura 'DD/MM/AAAA'    |*
 *|PAS |CNPJ_CPF_Proprietario|String |   7| |CNPJ/CPF do Proprietario         |*
 *+----+---------------------+-------+----+-+---------------------------------+*
 *|REL |Numero_da_Passagem   |String |  20|X|ID da Passagem/Ordem de Servico  |*
 *|REL |Codigo_do_Relato     |String |  20| |ID da Reclamacao do Cliente      |*
 *|REL |Descricao_do_Relato  |String | 100|X|ID da Passagem/Ordem de Servico  |*
 *+----+---------------------+-------+----+-+---------------------------------+*
 *|SER |Numero_da_Passagem   |String |  20|X|ID da Passagem/Ordem de Servico  |*
 *|SER |Codigo_do_Servico    |String |  20| |ID do Servico                    |*
 *|SER |Descricao_do_Servico |String | 100|X|Descricao do Servico             |*
 *|SER |Quantidade_do_servico|Float  | 9,2| |Quantidade                       |*
 *|SER |Valor_do_Servico     |Float  | 9,2| |Valor do Servico                 |*
 *+----+---------------------+-------+----+-+---------------------------------+*
 *|PEC |Numero_da_Passagem   |String |  20|X|ID da Passagem/Ordem de Servico  |*
 *|PEC |Codigo_da_Peca       |String |  20| |ID da Peca                       |*
 *|PEC |Descricao_da_Peca    |String | 100|X|Descricao da Peca                |*
 *|PEC |Quantidade_da_Peca   |Float  | 9,2| |Quantidade                       |*
 *|PEC |Valor_da_Peca        |Float  | 9,2| |Valor da Peca                    |*
 *+----+---------------------+-------+----+-+---------------------------------+*
 *|CLI |Numero_da_Passagem   |String |  20|X|ID da Passagem/Ordem de Servico  |*
 *|CLI |CNPJ_CPF             |String |  14| |CNPJ ou CPF do Cliente           |*
 *|CLI |Nome                 |String | 100|X|Nome do Cliente                  |*
 *|CLI |Endereco_Logradouro  |String | 100| |Endereco do Cliente              |*
 *|CLI |Numero               |String |  10| |Numero                           |*
 *|CLI |Bairro               |String |  50| |Bairro                           |*
 *|CLI |Cidade               |String |  50| |Cidade                           |*
 *|CLI |UF                   |String |   2| |UF                               |*
 *|CLI |CEP                  |Integer|   8| |CEP '99999999'                   |*
 *|CLI |Complemento          |String | 100| |Complemento                      |*
 *|CLI |Telefone             |String |  50| |Telefone                         |*
 *|CLI |IE_RG                |String |  20| |Inscricao Estadual ou RG         |*
 *|CLI |Email                |String | 100| |Email do Cliente                 |*
 *+----+---------------------+-------+----+-+---------------------------------+*
 *|VEI |Numero_da_Passagem   |String |  20|X|ID da Passagem/Ordem de Servico  |*
 *|VEI |Placa                |String |   7|X|Placa do Veiculo                 |*
 *|VEI |Chassi               |String |  17| |Chassi do Veiculo                |*
 *|VEI |Marca                |String |  20| |Marca do Veiculo                 |*
 *|VEI |Modelo               |String |  50| |Modelo do Veiculo                |*
 *|VEI |Versao               |String |  50| |Versao do Veiculo                |*
 *|VEI |Ano_Fabricacao       |Integer|   4| |Ano da Fabricacao 'AAAA'         |*
 *|VEI |Ano_Modelo           |Integer|   4| |Ano do Modelo 'AAAA'             |*
 *|VEI |Cor                  |String |  20| |Cor predominante do Veiculo      |*
 *|VEI |Combustivel          |String |  20| |Tipo de Combustivel do Veiculo   |*
 *|VEI |KM_Atual             |Integer|   9| |KM Atual do Veiculo              |*
 *|VEI |Descricao            |String | 100| |Descricao do Veiculo             |*
 *+----+---------------------+-------+----+-+---------------------------------+*
 *                                                                             *
 * Exemplo:                                                                    *
 * ========                                                                    *
 *                                                                             *
 * CAB;Email_da_Empresa;Numero_da_Passagem;                                    *
 * PAS;Numero_da_Passagem;Placa;Chassi;KM;Data_da_Passagem; ~                  *
 *     CNPJ_CPF_Proprietario;                                                  *
 * REL;Numero_da_Passagem;Codigo_do_Relato;Descricao_do_Relato;                *
 * SER;Numero_da_Passagem;Codigo_do_Servico;Descricao_do_Servico; ~            *
 *     Quantidade_do_servico;Valor_do_Servico;                                 *
 * PEC;Numero_da_Passagem;Codigo_da_Peca;Descricao_da_Peca; ~                  *
 *     Quantidade_da_Peca;Valor_da_Peca;                                       *
 * CLI;Numero_da_Passagem;CNPJ_CPF;Nome;Endereco_Logradouro;Numero;Bairro; ~   *
 *     Cidade;UF;CEP;Complemento;Telefone;IE_RG;Email;                         *
 * VEI;Numero_da_Passagem;Placa;Chassi;Marca;Modelo;Versao;Ano_Fabricacao; ~   *
 *     Ano_Modelo;Cor;Combustivel;KM_Atual;Descricao;                          *
 *                                                                             *
 ******************************************************************************/

def var wldirtxt     as   cha            no-undo.
def var wlarqcsv     as   cha            no-undo.
def var wlarqquo     as   cha            no-undo.

def var wllimp       as   int            no-undo.
def var wllidos      as   int            no-undo.
def var wlimport     as   cha            no-undo.
def var wldeli       as   cha            no-undo init ";".
def var wlidgpas     as   int            no-undo.
def var wlcgccpf     as   cha            no-undo.

def var wle          as   int            no-undo.
def var wli          as   int            no-undo.

/* Cabecalho */
def var wlcodemp     as   cha            no-undo.
def var wlidipas     as   cha            no-undo.
def var wlidpant     as   cha            no-undo.

/* Passagem */
def temp-table ttpas
   field idipas    as   cha
   field placa     as   cha
   field chassi    as   cha
   field kmatua    as   int
   field dtpass    as   dat
   field cgccpf    as   cha

   index ittpas01 idipas placa.

/* Relatos */
def temp-table ttrel
   field idipas    as   cha
   field codrel    as   cha
   field descri    as   cha

   index ittrel01 idipas codrel.

/* Servicos */
def temp-table ttser
   field idipas    as   cha
   field codser    as   cha
   field descri    as   cha
   field quanti    as   int
   field valser    as   dec

   index ittser01 idipas codser.

/* Pecas */
def temp-table ttpec
   field idipas    as   cha
   field codpec    as   cha
   field descri    as   cha
   field quanti    as   int
   field valpec    as   dec

   index ittpec01 idipas codpec.

/* Cliente */
def temp-table ttcli
   field idipas    as   cha
   field cgccpf    as   cha
   field nome      as   cha
   field endrua    as   cha
   field endnum    as   cha
   field endbai    as   cha
   field endcid    as   cha
   field enduf     as   cha
   field endcep    as   cha
   field endcom    as   cha
   field fone      as   cha
   field ierg      as   cha
   field email     as   cha

   index ittcli01 idipas cgccpf.

/* Veiculo */
def temp-table ttvei
   field idipas    as   cha
   field placa     as   cha
   field chassi    as   cha
   field marca     as   cha
   field modelo    as   cha
   field versao    as   cha
   field anofab    as   int
   field anomod    as   int
   field corvei    as   cha
   field combus    as   cha
   field kmatua    as   int
   field descri    as   cha

   index ittvei01 idipas placa.

/* Buffers */
def buffer buffccpv for fccpv.
def buffer buffcrpv for fcrpv.
def buffer buffcspv for fcspv.
def buffer buffcppv for fcppv.
def buffer buffcusu for fcusu.
def buffer buffcvei for fcvei.
def buffer buffckmv for fckmv.
def buffer buftbnum for tbnum.

/* funcoes */
function limpaString return character
        (p_string as cha, p_subval as log) forward.

/* Inicio da Logica de Importacao */
assign wldirtxt = ""
    /* wlarqcsv = wldirtxt + "cargaInicial.txt" */
    /* wlarqcsv = wldirtxt + "ReiDosCarburadores.txt" */
    /* wlarqcsv = wldirtxt + "Zavati.txt" */
       wlarqcsv = wldirtxt + "Bonilha.txt"
    /* wlarqcsv = wldirtxt + "AutoLins.txt" */
    /* wlarqcsv = wldirtxt + "Germano.txt" */
       wlarqquo = wldirtxt + "cargaInicial.quo"

       wlidpant = "".

output to "cargaLogBonilha.txt".
put unformatted "[" 
              + string(today,"99/99/9999")
              + " - "
              + string(time,"HH:MM:SS")
              + "] Inicio da Carga " chr(13) chr(10).

if search(wlarqcsv) <> ?
then do:
   if search(wlarqquo) <> ?
   then os-delete value(wlarqquo).

   if search("quoter") <> ?
   then unix silent quoter value(wlarqcsv) > value(wlarqquo).

   input from value(wlarqquo).
   repeat:
      import wlimport.

      wlimport = limpaString(wlimport, yes).
      
      if wlimport = ""
      then next.

      if lookup(wlimport, '"') > 0
      then next.

      wllimp = wllimp + 1.
      put screen row 3 column 5 string(wllimp, ">>>>>>>") 
        + " " + trim(entry(01, wlimport, wldeli)).

      case trim(entry(01, wlimport, wldeli)):
         when "CAB"
         then do:

            assign wlcodemp = entry(02, wlimport, wldeli)
                   wlidipas = entry(03, wlimport, wldeli)
                   wlcgccpf = "".

            if wlidpant <> "" and
               wlidpant <> wlidipas
            then do:
               /* Gravar dados nas tabelas */
               /* Verifica Usuario */
               find first fcusu where fcusu.e_mail = wlcodemp
                                   no-lock no-error.

               if not avail fcusu
               then return.

               /* Verifica se a Passagem ja existe */
               find first fccpv where fccpv.idusu  = fcusu.idusu
                                  and fccpv.idipas = wlidpant
                                  no-lock no-error.
               if avail fccpv
               then next.
               
               /* Verifica se existem dados da Passagem*/
               find first ttpas where ttpas.idipas = wlidpant
                                  no-lock no-error.
               if not avail ttpas
               then next.

               /* Grava Passagem */
               do for buftbnum, buffccpv, buffckmv:
                  /* Gera ID unico para Passagem */
                  find buftbnum where buftbnum.tipnum = "IDGPAS"
                                  exclusive-lock.
                  assign buftbnum.numgrd = buftbnum.numgrd + 1.

                  assign wlidgpas = buftbnum.numgrd.

                  create buffccpv.
                  assign buffccpv.idusu  = fcusu.idusu
                         buffccpv.idgpas = wlidgpas
                         buffccpv.exiofi = yes
                         buffccpv.placa  = ttpas.placa
                         buffccpv.chassi = ttpas.chassi
                         buffccpv.idipas = wlidpant
                         buffccpv.dtpsg  = ttpas.dtpass
                         buffccpv.cgccpf = ttpas.cgccpf
                         buffccpv.kilome = ttpas.kmatua
                         buffccpv.dttran = today
                         buffccpv.hortra = time.
               
                  find buffckmv where buffckmv.placa  = buffccpv.placa
                                  and buffckmv.idgpas = buffccpv.idgpas 
                                  no-lock no-error.
                  if not avail buffckmv
                  then do:
                     find first buffckmv where buffckmv.placa  = buffccpv.placa
                                           and buffckmv.dttran = buffccpv.dtpsg
                                           no-lock no-error.
                     if not avail buffckmv
                     then do:
                        find buffckmv where buffckmv.placa  = buffccpv.placa
                                        and buffckmv.kilome = buffccpv.kilome
                                        no-lock no-error.
                        if not avail buffckmv
                        then do:
                            create buffckmv.
                            assign buffckmv.placa  = buffccpv.placa
                                   buffckmv.dttran = buffccpv.dtpsg
                                   buffckmv.kilome = buffccpv.kilome
                                   buffckmv.idusu  = buffccpv.idusu
                                   buffckmv.idgpas = buffccpv.idgpas
                                   buffckmv.hortra = time.
                        end.
                     end.
                  end.
               
               end.

               /* Verifica se existem dados de Relatos */
               find first ttrel where ttrel.idipas = wlidpant
                                  no-lock no-error.
               if avail ttrel
               then do:
                  /* Grava Relatos */
                  do for buffcrpv:
                     for each ttrel where ttrel.idipas = wlidpant no-lock:
                        create buffcrpv.
                        assign buffcrpv.idgpas = wlidgpas
                               buffcrpv.idipas = ttrel.idipas
                               buffcrpv.descri = ttrel.descri
                               buffcrpv.dttran = today
                               buffcrpv.hortra = time.
                     end.
                  end.
               end.

               /* Verifica se existem dados de Servicos */
               find first ttser where ttser.idipas = wlidpant
                                  no-lock no-error.
               if avail ttser
               then do:
                  /* Grava Servicos */
                  do for buffcspv:
                     for each ttser where ttser.idipas = wlidpant no-lock:
                        create buffcspv.
                        assign buffcspv.idgpas = wlidgpas
                               buffcspv.idipas = ttser.idipas
                               buffcspv.codser = ttser.codser
                               buffcspv.descri = ttser.descri
                               buffcspv.quant  = ttser.quanti
                               buffcspv.vlunit = ttser.valser
                               buffcspv.dttran = today
                               buffcspv.hortra = time.
                     end.
                  end.
               end.

               /* Verifica se existem dados de Pecas */
               find first ttpec where ttpec.idipas = wlidpant
                                  no-lock no-error.
               if avail ttpec
               then do:
                  /* Grava Pecas */
                  do for buffcppv:
                     for each ttpec where ttpec.idipas = wlidpant no-lock:
                        create buffcppv.
                        assign buffcppv.idgpas = wlidgpas
                               buffcppv.idipas = ttpec.idipas
                               buffcppv.codpec = ttpec.codpec
                               buffcppv.descri = ttpec.descri
                               buffcppv.quant  = ttpec.quanti
                               buffcppv.vlunit = ttpec.valpec
                               buffcppv.dttran = today
                               buffcppv.hortra = time.
                     end.
                  end.
               end.

               /* Verifica se existem dados do Cliente */
               find first ttcli where ttcli.idipas = wlidpant
                                  no-lock no-error.
               if avail ttcli
               then do:
                  for each ttcli where ttcli.idipas = wlidpant no-lock:
                     /* So cadastra se existir valor no CNPJ/CPF ou E-mail */
                     if ttcli.cgccpf <> "" or ttcli.email <> ""
                     then do:
                        /* Verifica se o Cliente ja esta cadastrado */
                        if ttcli.cgccpf <> ""
                        then find first fcusu where fcusu.cgccpf = ttcli.cgccpf
                                                no-lock no-error.
                        if not avail fcusu and ttcli.email <> ""
                        then find first fcusu where fcusu.e_mail = ttcli.email
                                                no-lock no-error.
                        assign wlcgccpf = ttcli.cgccpf.

                        if not avail fcusu
                        then do:
                           /* Grava Cliente */
                           do for buftbnum, buffcusu:
                              /* Gera ID unico para o Cliente */
                              find buftbnum where tipnum = "USUARIO" exclusive-lock.
                              assign buftbnum.numgrd = buftbnum.numgrd + 1.

                              create buffcusu.
                              assign buffcusu.idusu  = buftbnum.numgrd
                                     buffcusu.idofi  = buffcusu.idusu
                                     buffcusu.cgccpf = ttcli.cgccpf
                                     buffcusu.nome   = ttcli.nome
                                     buffcusu.endrua = ttcli.endrua
                                     buffcusu.endnum = ttcli.endnum
                                     buffcusu.cidade = ttcli.endcid
                                     buffcusu.bairro = ttcli.endbai
                                     buffcusu.uf     = ttcli.enduf
                                     buffcusu.cep    = ttcli.endcep
                                     buffcusu.e_mail = ttcli.email
                                     buffcusu.fone   = ttcli.fone
                                     buffcusu.iestad = ttcli.ierg
                                     buffcusu.tipusu = "COF"
                                     buffcusu.dttran = today
                                     buffcusu.hortra = time.
                           end.
                        end.
                     end.
                  end.
               end.


               /* Verifica se existem dados do Veiculo */
               find first ttvei where ttvei.idipas = wlidpant
                                  no-lock no-error.
               if avail ttvei
               then do:
                  for each ttvei where ttvei.idipas = wlidpant no-lock:
                     /* So cadastra se existir valor no Placa ou Chassi */
                     if ttvei.placa <> "" or ttvei.chassi <> ""
                     then do:
                        /* Verifica se o Veiculo ja esta cadastrado */
                        if ttvei.placa <> ""
                        then find first fcvei where fcvei.placa = ttvei.placa
                                                 no-lock no-error.
                        if not avail fcvei and ttvei.chassi <> ""
                        then find first fcvei where fcvei.chassi = ttvei.chassi
                                                no-lock no-error.
                        if not avail fcvei
                        then do:
                           /* Grava Veiculo */
                           do for buffcvei:
                              create buffcvei.
                              assign buffcvei.placa  = ttvei.placa
                                     buffcvei.chassi = ttvei.chassi
                                     buffcvei.descri = ttvei.descri
                                     buffcvei.anomod = ttvei.anomod
                                     buffcvei.anofab = ttvei.anofab
                                     buffcvei.corvei = ttvei.corvei
                                     buffcvei.cgccpf = (if wlcgccpf <> ""
                                                        then wlcgccpf
                                                        else "")
                                     buffcvei.dttran = today
                                     buffcvei.hortra = time.
                           end.
                        end.
                     end.
                  end.
               end.

               /* limpa as tabelas para um novo regitro */
               empty temp-table ttpas.
               empty temp-table ttrel.
               empty temp-table ttser.
               empty temp-table ttpec.
               empty temp-table ttcli.
               empty temp-table ttvei.
               
               wllidos = wllidos + 1.
               if substr(string(wllidos,"999999"),5,2) = "00"
               then put unformatted "[" 
                                   + string(today,"99/99/9999")
                                   + " - "
                                   + string(time,"HH:MM:SS")
                                   + "] Passagens: " 
                                   + string(wllidos,">>>>>>") 
                                   + " Ultima Passagem: "
                                   + string(wlidpant) 
                                   + " (" + wlcodemp + ")"
                                   chr(13) chr(10).
               
            end.
         end.
         when "PAS"
         then do:
            create ttpas.
            assign ttpas.idipas =         entry(02, wlimport, wldeli)
                   ttpas.placa  = replace(entry(03, wlimport, wldeli),'-','')
                   ttpas.chassi =         entry(04, wlimport, wldeli)
                   ttpas.kmatua =     int(entry(05, wlimport, wldeli))
                   ttpas.dtpass =     (if entry(06, wlimport, wldeli) <> ""
                                       then date(entry(06, wlimport, wldeli))
                                    else ?)
                   ttpas.cgccpf =         entry(07, wlimport, wldeli).
         end.
         when "REL"
         then do:
            create ttrel.
            assign ttrel.idipas =         entry(02, wlimport, wldeli)
                   ttrel.codrel =         entry(03, wlimport, wldeli)
                   ttrel.descri =         entry(04, wlimport, wldeli).
         end.
         when "SER"
         then do:
            create ttser.
            assign ttser.idipas =         entry(02, wlimport, wldeli)
                   ttser.codser =         entry(03, wlimport, wldeli)
                   ttser.descri =         entry(04, wlimport, wldeli)
                   ttser.quanti =     dec(entry(05, wlimport, wldeli))
                   ttser.valser =     dec(entry(06, wlimport, wldeli)).
         end.
         when "PEC"
         then do:
            create ttpec.
            assign ttpec.idipas =         entry(02, wlimport, wldeli)
                   ttpec.codpec =         entry(03, wlimport, wldeli)
                   ttpec.descri =         entry(04, wlimport, wldeli)
                   ttpec.quanti =     dec(entry(05, wlimport, wldeli))
                   ttpec.valpec =     dec(entry(06, wlimport, wldeli)).
         end.
         when "CLI"
         then do:
            create ttcli.
            assign ttcli.idipas =         entry(02, wlimport, wldeli)
                   ttcli.cgccpf =         entry(03, wlimport, wldeli)
                   ttcli.nome   =         entry(04, wlimport, wldeli)
                   ttcli.endrua =         entry(05, wlimport, wldeli)
                   ttcli.endnum =         entry(06, wlimport, wldeli)
                   ttcli.endbai =         entry(07, wlimport, wldeli)
                   ttcli.endcid =         entry(08, wlimport, wldeli)
                   ttcli.enduf  =         entry(09, wlimport, wldeli).
            
            assign ttcli.endcep =         entry(10, wlimport, wldeli)
                   ttcli.endcep = replace(ttcli.endcep, '-', '')
                   ttcli.endcep =  substr(ttcli.endcep, 1, 8).
            
            if ttcli.endcep = "00000000"
            then ttcli.endcep = "".
                   
            assign ttcli.endcom =         entry(11, wlimport, wldeli)
                   ttcli.fone   =         entry(12, wlimport, wldeli)
                   ttcli.ierg   =         entry(13, wlimport, wldeli)
                   ttcli.email  =         entry(14, wlimport, wldeli).
         end.
         when "VEI"
         then do:
            /*
            if num-entries(wlimport,";") < wle
            then do wli = num-entries(wlimport,";") to wle:
               wlimport = wlimport + ";".
            end.
            */
            create ttvei.
            assign ttvei.idipas =         entry(02, wlimport, wldeli)
                   ttvei.placa  = replace(entry(03, wlimport, wldeli),'-','')
                   ttvei.chassi =         entry(04, wlimport, wldeli)
                   ttvei.marca  =         entry(05, wlimport, wldeli)
                   ttvei.modelo =         entry(06, wlimport, wldeli)
                   ttvei.versao =         entry(07, wlimport, wldeli)
                   ttvei.anofab = int(replace(entry(08, wlimport, wldeli),'/',''))
                   ttvei.anomod = int(replace(entry(09, wlimport, wldeli),'/',''))
                   ttvei.corvei =         entry(10, wlimport, wldeli)
                   ttvei.combus =         entry(11, wlimport, wldeli)
                   ttvei.kmatua =     int(replace(entry(12, wlimport, wldeli),'KM:',''))
                   ttvei.descri =         entry(13, wlimport, wldeli).
         end.
         otherwise next.
      end.

      put screen row 4 column 5 string(wlidpant).
      put screen row 5 column 5 string(wlidipas).
      
      assign wlidpant = wlidipas.

   end.
end.
/*
message "Passagens: " wllidos.
*/
put unformatted "[" 
              + string(today,"99/99/9999")
              + " - "
              + string(time,"HH:MM:SS")
              + "] Termino da Carga " chr(13) chr(10).


output to close.

return.

/*****************************************************************************/
/************************* F  U  N  C  T  I  O  N  S *************************/
/*****************************************************************************/

/******************************************************************************
 * Funcao....: limpaString                                                    *
 * Descricao.: Substitui ou Retorna se existem caracteres invalidos           *
 * Autor.....: Jose Augusto Freire                                            *
 * Data......: 07/04/2017                                                     *
 *                                                                            *
 * Entrada: p_string    - Texto a ser verificado                              *
 *          p_subval    - Substitui (yes) / Valida (no)                       *
 *                                                                            *
 * Retorno: string sem caracteres especiais ou                                *
 *          mensagem de erro com os caracteres                                *
 *                                                                            *
 * Exemplo:                                                                   *
 *         wltexto = "Caixa D'Água".                                          *
 *         wltexto = limpaString(wltexto, yes).                               *
 *         Retorno = "Caixa D-Agua".                                          *
 *                                                                            *
 *****************************************************************************/
function limpaString return character (p_string as cha, p_subval as log):
   def var wli      as int no-undo.
   def var wlcaract as cha no-undo case-sensitive.
   def var wlinval  as cha no-undo case-sensitive.
   def var wlvalido as cha no-undo.
   def var wlmsgerr as cha no-undo.
   def var wlstring as cha no-undo case-sensitive.
   def var wlchars  as cha no-undo.
   def var wlascs   as cha no-undo.
   def var wlncinvs as int no-undo.

   assign wlinval  = 'äãàáâÄÃÀÁÂÅ'
                   + 'ëèéêËÈÉÊ'
                   + 'ïìíîÏÌÍÎ'
                   + 'öõòóôÖÕÒÓÔ'
                   + 'üùúûÜÙÚÛ'
                   + 'ñÑçÇÐÝß'
                   + '"&~^<>'
                   + "ªº°£§©Ææ'".
   assign wlvalido = 'aaaaaAAAAAA'
                   + 'eeeeEEEE'
                   + 'iiiiIIII'
                   + 'oooooOOOOO'
                   + 'uuuuUUUU'
                   + 'nNcCDYB'
                   + ' e  ()'
                   + 'aooLSCAa '.

/* p_string = wlinval. */

   do wli = 1 to length(p_string):
      wlcaract = substr(p_string, wli, 1).
      wlcaract = codepage-convert(wlcaract, session:charset, "ibm850").

      if index(wlinval, wlcaract) > 0
      then do:
         if p_subval = true
         then do:
            if      asc(wlcaract) = 160 then wlcaract = "a".
            else if asc(wlcaract) = 161 then wlcaract = "u".
            else if asc(wlcaract) = 162 then wlcaract = "o".
            else if asc(wlcaract) = 163 then wlcaract = "u".
            else if asc(wlcaract) = 164 then wlcaract = "n".
            else if asc(wlcaract) = 165 then wlcaract = "N".
            else if asc(wlcaract) = 169 then wlcaract = "(r)".
            else if asc(wlcaract) = 184 then wlcaract = "(c)".
            else if asc(wlcaract) = 198 then wlcaract = "A". /*"AE".*/
            else if asc(wlcaract) = 230 then wlcaract = "a". /*"ae".*/
            else wlcaract = substr(wlvalido,index(wlinval, wlcaract),1).
         end.
         else assign wlchars  = wlchars  + wlcaract
                     wlascs   = wlascs   + string(asc(wlcaract)) + "/"
                     wlncinvs = wlncinvs + 1.
      end.
      wlstring = wlstring + wlcaract.
   end.

   if wlchars <> ""
   then do:
      if substr(wlascs,length(wlascs), 1) = "/"
      then wlascs = substr(wlascs,1 , length(wlascs) - 1).

      wlascs = " (" + wlascs + ")".

      wlmsgerr = string(wlncinvs)
               + " Caracter(es) '" + wlchars + "' invalido(s)!".
   end.

   if wlmsgerr <> ""
   then return wlmsgerr.
   else return caps(wlstring).

end function. /* function limpaString */
