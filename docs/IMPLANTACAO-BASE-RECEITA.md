# Radar CNPJ — implantação nacional (Receita Federal)

## O que está no GitHub
- `database/schema.sql`: esquema MySQL indexado (Empresas, Estabelecimentos, Simples/MEI, CNAEs, municípios, controle de cargas).
- `scripts/importar_receita.py`: trabalhador Python de carga dos ZIPs oficiais ou URL de diretório de arquivos ZIP.
- `site/api/search.php`: endpoint PHP/MySQL autenticado por Bearer com pesquisa paginada e CSV, até 100 mil linhas por chamada.
- `site/busca/index.html`: interface de pesquisa online, filtros encadeados e exportação CSV autenticada.
- `deploy-config/config.example.php`: exemplo de configuração PHP privada.

## Necessário antes do primeiro resultado
1. Provisionar **banco MySQL/MariaDB separado**, compatível com volume nacional (dezenas de milhões de linhas, índices e discos); **não** assumir que o plano compartilhado Locaweb suporta toda a base.
2. Executar `database/schema.sql` nesse banco.
3. Criar `radarcnpj-config.php` em **diretório pai de public_html** com DSN, usuário e senha do banco, chave longa aleatória. Não incluir no GitHub. Validar caminho com hospedagem.
4. Instalar dependências do worker: `python -m pip install pymysql`. Variáveis: `RADAR_DB_HOST`, `RADAR_DB_NAME`, `RADAR_DB_USER`, `RADAR_DB_PASSWORD`.
5. Obter os arquivos oficiais da competência escolhida: https://www.gov.br/receitafederal/dados/cnpj-metadados.pdf e https://www.gov.br/receitafederal/pt-br/acesso-a-informacao/dados-abertos .
6. Processar os arquivos com `python scripts/importar_receita.py --directory /pasta/zip --competencia 2026-09`, ou fornecer `--source-url` apontando para **diretório público de arquivos ZIP**, sujeito à acessibilidade HTTP desse servidor.
7. Verificar no banco se a última carga está `complete` e se os totais correspondem à lista de arquivos fornecidos.
8. Testar busca por GO, município e CNAE, conferir resultados reais e testar exportação limitada antes de abrir para usuários.

## Restrições reais
- O plano FTP/site estático não fornece, por si só, banco de dezenas de milhões de empresas nem trabalhador para downloads e carga de dezenas de GB.
- A carga nacional consome disco, tempo de CPU, memória e banda; deve ocorrer em trabalhador dedicado ou infraestrutura apropriada.
- O processo atual faz **upsert**, não remove dados de competências anteriores. **Ainda não está apto a atualizações mensais de snapshot em produção** sem substituição atômica ou limpeza das linhas ausentes.
- A tabela de sócios ainda não está indexada nesta versão; demanda controle especial de LGPD e função comercial definida.
- CNAE secundário usa pesquisa textual no campo CSV, com eficiência inferior à tabela relacional; otimização pendente.
- Município depende de código da Receita; a interface envia nome legível e o servidor cruza com tabela Municípios da Receita.
- O mecanismo de exportação limita a 100 mil linhas por chamada para conter a carga. Para grandes conjuntos, falta exportação assíncrona por lotes (sem depender do navegador).
- A interface exige token manual e o sistema ainda precisa de autenticação por usuários, cotas, auditoria e isolamento por clientes para futura comercialização.
- **Não publicar como ferramenta nacional operante enquanto a carga não tiver sido executada e verificada.**

## Fonte dos dados
Usar os arquivos públicos da Receita Federal de Empresas, Estabelecimentos, Simples e tabelas de municípios e CNAEs. A carga não deve ser confundida com a API de consulta individual de CNPJ e não permite inferir pendências tributárias nem conformidade NR-1.
