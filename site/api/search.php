<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$configPath = dirname(__DIR__, 3) . '/radarcnpj-config.php';
if (!is_file($configPath)) {
 http_response_code(503); header('Content-Type: application/json; charset=utf-8');
 echo json_encode(['error'=>'Banco não configurado. Configure radarcnpj-config.php fora da pasta pública.']); exit;
}
$config = require $configPath;
$expected = (string)($config['api_key'] ?? '');
$provided = '';
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) $provided=trim($m[1]);
if ($expected==='' || $provided==='' || !hash_equals($expected,$provided)) {
 http_response_code(401); header('Content-Type: application/json; charset=utf-8');
 echo json_encode(['error'=>'Acesso não autorizado.']); exit;
}
try {
 $pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES=>false,
 ]);
} catch (Throwable $e) {
 http_response_code(503); header('Content-Type: application/json; charset=utf-8');
 echo json_encode(['error'=>'Banco indisponível.']); exit;
}
$g=fn($k)=>trim((string)($_GET[$k] ?? ''));
$filters=[];$params=[];
function add(array &$filters,array &$params,string $sql,string $name,$value):void {
 $filters[]=$sql; $params[$name]=$value;
}
if ($v=$g('uf')) {if(!preg_match('/^[A-Z]{2}$/',$v)) bad(); add($filters,$params,'s.uf = :uf','uf',$v);}
if ($v=$g('municipio_codigo')) {if(!preg_match('/^\d{1,4}$/',$v)) bad();add($filters,$params,'s.municipio_codigo = :municipio','municipio',str_pad($v,4,'0',STR_PAD_LEFT));}
if ($v=$g('municipio')) {if(mb_strlen($v)>150)bad();add($filters,$params,'m.descricao = :municipio_nome','municipio_nome',$v);}
if ($v=$g('cnae')) {if(!preg_match('/^\d{2,7}$/',$v)) bad();add($filters,$params,'s.cnae_principal LIKE :cnae','cnae',$v.'%');}
if ($v=$g('cnae_secundario')) {if(!preg_match('/^\d{2,7}$/',$v)) bad();add($filters,$params,'s.cnae_secundarios LIKE :sec','sec','%'.$v.'%');}
if ($v=$g('situacao')) {if(!preg_match('/^\d{2}$/',$v)) bad();add($filters,$params,'s.situacao = :situacao','situacao',$v);}
if ($v=$g('porte')) {if(!preg_match('/^\d{2}$/',$v)) bad();add($filters,$params,'e.porte = :porte','porte',$v);}
if ($v=$g('matriz_filial')) {if(!in_array($v,['1','2'],true)) bad();add($filters,$params,'s.matriz_filial = :matriz','matriz',$v);}
if ($v=$g('natureza')) {if(!preg_match('/^\d{4}$/',$v)) bad();add($filters,$params,'e.natureza_juridica = :natureza','natureza',$v);}
if ($v=$g('mei')) {if(!in_array($v,['S','N'],true)) bad();add($filters,$params,'sm.opcao_mei = :mei','mei',$v);}
if ($v=$g('simples')) {if(!in_array($v,['S','N'],true)) bad();add($filters,$params,'sm.opcao_simples = :simples','simples',$v);}
foreach(['abertura_de'=>'>=','abertura_ate'=>'<='] as $field=>$op) if($v=$g($field)){
 if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)||!strtotime($v))bad();add($filters,$params,"s.data_inicio $op :$field",$field,$v);
}
if($v=$g('nome')) {if(mb_strlen($v)>120)bad();$filters[]='(e.razao_social LIKE :nome OR s.nome_fantasia LIKE :nome2)';$params['nome']='%'.$v.'%';$params['nome2']='%'.$v.'%';}
if($v=$g('cep')) {if(!preg_match('/^\d{2,8}$/',$v))bad();add($filters,$params,'s.cep LIKE :cep','cep',$v.'%');}
if($v=$g('capital_min')) {if(!is_numeric($v)||floatval($v)<0)bad();add($filters,$params,'e.capital_social >= :capital_min','capital_min',$v);}
if($v=$g('capital_max')) {if(!is_numeric($v)||floatval($v)<0)bad();add($filters,$params,'e.capital_social <= :capital_max','capital_max',$v);}
if ($g('tem_email')==='1') $filters[]="s.email IS NOT NULL AND s.email <> ''";
if ($g('tem_telefone')==='1') $filters[]="s.telefone1 IS NOT NULL AND s.telefone1 <> ''";
$where=$filters?' WHERE '.implode(' AND ',$filters):'';
$joins=' FROM radar_estabelecimentos s JOIN radar_empresas e ON e.cnpj_basico=s.cnpj_basico LEFT JOIN radar_simples sm ON sm.cnpj_basico=s.cnpj_basico LEFT JOIN radar_municipios m ON m.codigo=s.municipio_codigo';
$columns='s.cnpj,e.razao_social,s.nome_fantasia,s.uf,m.descricao AS municipio,s.municipio_codigo,s.cnae_principal,s.cnae_secundarios,s.situacao,s.data_inicio,s.matriz_filial,e.porte,e.natureza_juridica,e.capital_social,sm.opcao_simples,sm.opcao_mei,sm.data_opcao_mei,s.tipo_logradouro,s.logradouro,s.numero,s.complemento,s.bairro,s.cep,s.telefone1,s.telefone2,s.email,s.data_situacao,s.motivo_situacao,s.situacao_especial,s.data_situacao_especial';
$mode=$g('mode') ?: 'search';
if (!in_array($mode,['search','export','metadata'],true))bad();
if($mode==='metadata') {
 $out=['source'=>'Dados abertos do CNPJ / Receita Federal','latest'=>null];
 try {$out['latest']=$pdo->query("SELECT competencia,concluido_em,empresas,estabelecimentos,simples FROM radar_cargas WHERE status='complete' ORDER BY concluido_em DESC LIMIT 1")->fetch() ?: null;} catch(Throwable $e){}
 header('Content-Type: application/json; charset=utf-8');echo json_encode($out);exit;
}
if (!$filters) {http_response_code(400);header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>'Defina pelo menos um filtro para evitar pesquisa nacional irrestrita.']);exit;}
function bindAll(PDOStatement $stmt,array $params):void {foreach($params as $key=>$value)$stmt->bindValue(':'.$key,$value);}
if ($mode==='export') {
 $limit=max(1,min(100000,(int)($config['max_export_rows']??100000)));
 set_time_limit(120);
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="radarcnpj-'.date('Ymd-His').'.csv"');
 echo "\xEF\xBB\xBF";$fp=fopen('php://output','w');$stmt=$pdo->prepare("SELECT $columns $joins $where ORDER BY s.cnpj LIMIT $limit");bindAll($stmt,$params);$stmt->execute();$first=true;
 while($row=$stmt->fetch()) {if($first){fputcsv($fp,array_keys($row),';');$first=false;}foreach($row as &$value)if(is_string($value)&&preg_match('/^[=+@\-\t\r]/',$value))$value="'".$value;unset($value);fputcsv($fp,$row,';');}
 fclose($fp);exit;
}
$page=max(1,min(100000,(int)($g('page')?:1)));$size=max(1,min(200,(int)($g('size')?:50)));$offset=($page-1)*$size;
try {
 $count=$pdo->prepare("SELECT COUNT(*) $joins $where");bindAll($count,$params);$count->execute();$total=(int)$count->fetchColumn();
 $stmt=$pdo->prepare("SELECT $columns $joins $where ORDER BY s.cnpj LIMIT :limit OFFSET :offset");bindAll($stmt,$params);
 $stmt->bindValue(':limit',$size,PDO::PARAM_INT);$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);$stmt->execute();
 header('Content-Type: application/json; charset=utf-8');echo json_encode(['total'=>$total,'page'=>$page,'size'=>$size,'results'=>$stmt->fetchAll()],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
} catch(Throwable $e){http_response_code(500);header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>'Falha ao executar consulta no índice.']);}
function bad():void{http_response_code(400);header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>'Filtro inválido.']);exit;}
