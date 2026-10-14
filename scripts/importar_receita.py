#!/usr/bin/env python3
"""Carga operacional dos dados públicos CNPJ (Receita Federal) para MySQL.
Executar em servidor/worker próprio; NÃO faz upload pelo navegador.
Requer: pip install pymysql
Ex.: python scripts/importar_receita.py --directory /dados/receita/2026-09 --competencia 2026-09
Ou:  python scripts/importar_receita.py --source-url URL_DIRETORIO_OFICIAL --competencia 2026-09
Credenciais por variáveis RADAR_DB_HOST, RADAR_DB_NAME, RADAR_DB_USER, RADAR_DB_PASSWORD.
"""
import argparse, csv, io, os, re, sys, urllib.parse, urllib.request, zipfile
from pathlib import Path
from html.parser import HTMLParser
import pymysql

class Links(HTMLParser):
    def __init__(self): super().__init__(); self.links=[]
    def handle_starttag(self,tag,attrs):
        if tag=="a":
            href=dict(attrs).get("href","")
            if href.lower().endswith(".zip"): self.links.append(href)

def fetch_archives(source, destination):
    destination.mkdir(parents=True, exist_ok=True)
    request=urllib.request.Request(source,headers={"User-Agent":"RadarCNPJ/1.0"})
    with urllib.request.urlopen(request,timeout=45) as resp:
        text=resp.read().decode("utf-8","ignore")
    parser=Links(); parser.feed(text)
    if not parser.links: raise RuntimeError("Diretório não apresenta links ZIP. Verifique URL e permissões da fonte oficial.")
    for ref in parser.links:
        url=urllib.parse.urljoin(source,ref); filename=Path(urllib.parse.urlparse(url).path).name
        if not re.search(r"(empre|estabele|simples|municip|cnae)",filename,re.I): continue
        dest=destination/filename
        if dest.exists() and dest.stat().st_size: continue
        print("Baixando:",filename,flush=True)
        with urllib.request.urlopen(urllib.request.Request(url,headers={"User-Agent":"RadarCNPJ/1.0"}),timeout=120) as res, dest.open("wb") as out:
            while chunk:=res.read(1024*1024):out.write(chunk)

def text(v):
    return (v or "").strip() or None
def dt(v):
    value=(v or "").strip()
    return value if re.fullmatch(r"\d{8}",value) and value!="00000000" and value[4:6]!="00" and value[6:]!="00" and (value:=value[:4]+"-"+value[4:6]+"-"+value[6:]) else None
def decimal(v):
    try:return (v or "0").strip().replace(",",".")
    except:return "0"

FIELDS={
"empresas":("radar_empresas",["cnpj_basico","razao_social","natureza_juridica","qualificacao_responsavel","capital_social","porte","ente_federativo"]),
"estabelecimentos":("radar_estabelecimentos",["cnpj","cnpj_basico","matriz_filial","nome_fantasia","situacao","data_situacao","motivo_situacao","nome_cidade_exterior","pais","data_inicio","cnae_principal","cnae_secundarios","tipo_logradouro","logradouro","numero","complemento","bairro","cep","uf","municipio_codigo","telefone1","telefone2","fax","email","situacao_especial","data_situacao_especial"]),
"simples":("radar_simples",["cnpj_basico","opcao_simples","data_opcao_simples","data_exclusao_simples","opcao_mei","data_opcao_mei","data_exclusao_mei"]),
"municipios":("radar_municipios",["codigo","descricao"]),
"cnaes":("radar_cnaes",["codigo","descricao"]),
}
def transform(kind,v):
    if kind=="empresas":
        if len(v)<7:return None
        return (v[0],text(v[1]),text(v[2]),text(v[3]),decimal(v[4]),text(v[5]),text(v[6]))
    if kind=="estabelecimentos":
        if len(v)<30:return None
        return (v[0]+v[1]+v[2],v[0],text(v[3]),text(v[4]),text(v[5]),dt(v[6]),text(v[7]),text(v[8]),text(v[9]),dt(v[10]),text(v[11]),text(v[12]),text(v[13]),text(v[14]),text(v[15]),text(v[16]),text(v[17]),text(v[18]),text(v[19]),text(v[20]),text(v[21]),text(v[22]),text(v[23]),text(v[24]),dt(v[25]))
    if kind=="simples":
        if len(v)<7:return None
        return (v[0],text(v[1]),dt(v[2]),dt(v[3]),text(v[4]),dt(v[5]),dt(v[6]))
    if len(v)<2:return None
    return (v[0],text(v[1]))
def kind_from_name(filename):
    name=filename.lower()
    for key in ("estabelecimentos","empresas","simples","municipios","cnaes"):
        if key[:-1] in name:return key
    return None
def run(args):
    db=pymysql.connect(host=os.environ["RADAR_DB_HOST"],user=os.environ["RADAR_DB_USER"],password=os.environ["RADAR_DB_PASSWORD"],database=os.environ["RADAR_DB_NAME"],charset="utf8mb4",autocommit=False,read_timeout=180,write_timeout=180)
    directory=Path(args.directory)
    if args.source_url:fetch_archives(args.source_url,directory)
    files=sorted(directory.glob("*.zip"))
    selected=[(kind_from_name(p.name),p) for p in files]
    selected=[(k,p) for k,p in selected if k]
    if not selected:raise RuntimeError("Nenhum ZIP reconhecido. Verifique os nomes dos arquivos oficiais.")
    print("Arquivos:",len(selected))
    processed={}
    with db.cursor() as cur:
        cur.execute("INSERT INTO radar_cargas (competencia,origem,status) VALUES (%s,%s,'loading')",(args.competencia,args.source_url or str(directory)))
        load_id=cur.lastrowid
    db.commit()
    try:
        for kind,path in selected:
            table,fields=FIELDS[kind]
            sql="INSERT INTO "+table+" ("+",".join(fields)+") VALUES ("+",".join(["%s"]*len(fields))+") ON DUPLICATE KEY UPDATE "+",".join(c+"=VALUES("+c+")" for c in fields[1:])
            print("Processando",path.name,flush=True);total=0
            with zipfile.ZipFile(path) as z:
                for name in z.namelist():
                    if name.endswith("/"):continue
                    with z.open(name) as raw,io.TextIOWrapper(raw,encoding="latin1",errors="replace",newline="") as source:
                        batch=[]
                        for row in csv.reader(source,delimiter=";",quotechar='"'):
                            record=transform(kind,row)
                            if record is None:continue
                            batch.append(record)
                            if len(batch)>=1000:
                                with db.cursor() as cur:cur.executemany(sql,batch)
                                db.commit();total+=len(batch);batch=[]
                                if total%100000==0:print(kind,total,flush=True)
                        if batch:
                            with db.cursor() as cur:cur.executemany(sql,batch)
                            db.commit();total+=len(batch)
            processed[kind]=processed.get(kind,0)+total
        with db.cursor() as cur:
            cur.execute("UPDATE radar_cargas SET status='complete',concluido_em=NOW(),empresas=%s,estabelecimentos=%s,simples=%s WHERE id=%s",(processed.get("empresas",0),processed.get("estabelecimentos",0),processed.get("simples",0),load_id))
        db.commit()
        print("Carga concluída:",processed)
    except Exception:
        db.rollback()
        with db.cursor() as cur:cur.execute("UPDATE radar_cargas SET status='failed' WHERE id=%s",(load_id,))
        db.commit();raise
    finally:db.close()
if __name__=="__main__":
    p=argparse.ArgumentParser();p.add_argument("--directory",default="./receita");p.add_argument("--source-url");p.add_argument("--competencia",required=True);run(p.parse_args())
