def var wldirtxt     as   cha            no-undo.
def var wlarqcsv     as   cha            no-undo.
def var wlarqquo     as   cha            no-undo.

def var wllidos      as   int            no-undo.
def var wlimport     as   cha            no-undo.
def var wldeli       as   cha            no-undo init ";".

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


/* funcoes */
function limpaString return character
        (p_string as cha, p_subval as log) forward.

/* Inicio da Logica de Importacao */
assign wldirtxt = ""
       wlarqcsv = wldirtxt + "cargaInicial.csv"
       wlarqquo = wldirtxt + "cargaInicial.quo"

       wlidpant = "".

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

      wllidos = wllidos + 1.

      case trim(entry(01, wlimport, wldeli)):
         when "CAB"
         then do:

            assign wlcodemp = entry(02, wlimport, wldeli)
                   wlidipas = entry(03, wlimport, wldeli).

            if wlidpant <> "" and
               wlidpant <> wlidipas
            then do:
               /* Gravar dados nas tabelas */

               /* limpa as tabelas para um novo regitro */
               empty temp-table ttpas.
               empty temp-table ttrel.
               empty temp-table ttser.
               empty temp-table ttcli.
               empty temp-table ttvei.
            end.
         end.
         when "PAS"
         then do:
            create ttpas.
            assign ttpas.idipas =      entry(02, wlimport, wldeli)
                   ttpas.placa  =      entry(03, wlimport, wldeli)
                   ttpas.chassi =      entry(04, wlimport, wldeli)
                   ttpas.kmatua =  int(entry(05, wlimport, wldeli))
                   ttpas.dtpass =  (if entry(06, wlimport, wldeli) <> ""
                                    then date(entry(06, wlimport, wldeli))
                                    else ?)
                   ttpas.cgccpf =      entry(07, wlimport, wldeli).
         end.
         when "REL"
         then do:
            create ttrel.
            assign ttrel.idipas =      entry(02, wlimport, wldeli)
                   ttrel.codrel =      entry(03, wlimport, wldeli)
                   ttrel.descri =      entry(04, wlimport, wldeli).
         end.
         when "SER"
         then do:
            create ttser.
            assign ttser.idipas =      entry(02, wlimport, wldeli)
                   ttser.codser =      entry(03, wlimport, wldeli)
                   ttser.descri =      entry(04, wlimport, wldeli)
                   ttser.quanti =  dec(entry(05, wlimport, wldeli))
                   ttser.valser =  dec(entry(06, wlimport, wldeli)).
         end.
         when "PEC"
         then do:
            create ttpec.
            assign ttpec.idipas =      entry(02, wlimport, wldeli)
                   ttpec.codpec =      entry(03, wlimport, wldeli)
                   ttpec.descri =      entry(04, wlimport, wldeli)
                   ttpec.quanti =  dec(entry(05, wlimport, wldeli))
                   ttpec.valpec =  dec(entry(06, wlimport, wldeli)).
         end.
         when "CLI"
         then do:
            create ttcli.
            assign ttcli.idipas =      entry(02, wlimport, wldeli)
                   ttcli.cgccpf =      entry(03, wlimport, wldeli)
                   ttcli.nome   =      entry(04, wlimport, wldeli)
                   ttcli.endrua =      entry(05, wlimport, wldeli)
                   ttcli.endnum =      entry(06, wlimport, wldeli)
                   ttcli.endbai =      entry(07, wlimport, wldeli)
                   ttcli.endcid =      entry(08, wlimport, wldeli)
                   ttcli.enduf  =      entry(09, wlimport, wldeli)
                   ttcli.endcep =      entry(10, wlimport, wldeli)
                   ttcli.endcom =      entry(11, wlimport, wldeli)
                   ttcli.fone   =      entry(12, wlimport, wldeli)
                   ttcli.ierg   =      entry(13, wlimport, wldeli)
                   ttcli.email  =      entry(14, wlimport, wldeli).
         end.
         when "VEI"
         then do:
            create ttvei.
            assign ttvei.idipas =      entry(02, wlimport, wldeli)
                   ttvei.placa  =      entry(03, wlimport, wldeli)
                   ttvei.chassi =      entry(04, wlimport, wldeli)
                   ttvei.marca  =      entry(05, wlimport, wldeli)
                   ttvei.modelo =      entry(06, wlimport, wldeli)
                   ttvei.versao =      entry(07, wlimport, wldeli)
                   ttvei.anofab =  int(entry(08, wlimport, wldeli))
                   ttvei.anomod =  int(entry(09, wlimport, wldeli))
                   ttvei.corvei =      entry(10, wlimport, wldeli)
                   ttvei.combus =      entry(11, wlimport, wldeli)
                   ttvei.kmatua =  int(entry(12, wlimport, wldeli))
                   ttvei.descri =      entry(13, wlimport, wldeli).
         end.
         otherwise next.
      end.

      assign wlidpant = wlidipas.

   end.
end.



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
 *         wltexto = "Caixa D'?gua".                                          *
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

   assign wlinval  = '???????????'
                   + '????????'
                   + '????????'
                   + '??????????'
                   + '????????'
                   + '???????'
                   + '"&~^<>'
                   + "????????'".
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
   else return wlstring.

end function. /* function limpaString */
