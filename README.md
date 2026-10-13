# Radar CNPJ — Compass Rose Systems

Ferramenta independente de consulta de CNPJ e carteira inicial de prospecção utilizada pelo GEB Empresarial.

- **URL prevista:** https://radarcnpj.compassrosesystems.com.br/
- **Destino Locaweb:** `public_html/radarcnpj`
- **Origem do deploy:** `site/`
- **Consulta:** BrasilAPI (integração no navegador; sujeita à disponibilidade, limitações e CORS da fonte).
- **Armazenamento MVP:** localStorage local de cada navegador; não há login nem base compartilhada.
- **Busca por segmento / região no universo de CNPJs:** ainda não implementada (exige base indexada).

## Publicação

Workflow `.github/workflows/deploy.yml` é acionado em push na branch `main`. Configurar **GitHub Actions Secrets** deste repositório: `HOST`, `USER` e `PASS` (FTP da Locaweb). Segredos de outro repositório não são transferidos automaticamente.

O workflow publica o conteúdo de `site/` em `public_html/radarcnpj/` e não exclui outros arquivos do destino. Confirmar o diretório inicial da conta FTP da Locaweb e o apontamento DNS/subdomínio.

## Segurança

**O MVP não tem autenticação.** Para uso interno, restringir acesso no servidor antes de utilizar a carteira comercial. Por ser um repositório público, não gravar senhas, listas de empresas, credenciais, exportações ou dados pessoais no GitHub.

A aplicação não verifica certidões, pendências de DASN, obrigações de PGR ou conformidade de NR-1.
