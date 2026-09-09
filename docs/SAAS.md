# Sierra SaaS — operação do piloto

## Estado e arquitetura

O modo SaaS é opt-in (`SAAS_ENABLED=false` por padrão). A operação existente não foi migrada nem publicada por esta alteração.

As duas APIs continuam separadas. O banco central é acessado exclusivamente pela conexão `saas_central`; cada empresa usa seu próprio banco MySQL, incluindo usuários, perfis, tokens e dados operacionais. `ResolveTenant` inicializa o ambiente pelo host antes dos middlewares de autenticação, auditoria e consultas. Hosts desconhecidos não têm banco padrão de fallback.

A API operacional está organizada em `app/Domain/{Base,Estoque,Financeiro,Vendas,Assistencia,Comunicacao,Agenda}`. Models permanecem compartilhados durante a extração gradual dos contratos; não são microsserviços independentes. O cancelamento de recebíveis e as reservas possuem contratos entre Financeiro, Vendas e Estoque, preservando a transação da venda. Rotas públicas existentes foram mantidas; namespaces PHP internos mudaram, exigindo regenerar o autoload no deploy.

O frontend contém os seis módulos em `src/modules`, além de `saas`. Sessões SaaS recebem `tenant` e `modules` no login e em `/auth/me`. Menus, rotas e a página inicial respeitam os módulos, e a API é a autoridade final. A empresa pode contratar apenas a base. Vendas exige Estoque e Financeiro; Assistência exige Vendas. Jornadas dependem do módulo de origem.

O runtime `app/Saas` é distribuído nas duas APIs para preservar seus deploys independentes. Alterações nesses arquivos devem ser aplicadas nas duas cópias; o verificador no workspace compara as cópias. `PlatformController` e `PlatformAuth` existem somente em autenticação.

## Configuração e primeiro administrador

1. Preparar um MySQL exclusivo de testes/homologação antes de qualquer migração real. Criar o banco central e os usuários MySQL de runtime e provisionamento. O provisionador necessita permissão de criação de bancos; o runtime necessita acesso aos bancos de clientes provisionados. Perfis adicionais podem usar credenciais específicas por cliente.
2. Configurar as duas APIs com os mesmos parâmetros centrais e domínio-base. Configurar `SAAS_STORAGE_ROOT` como volume compartilhado pelas APIs e workers, fora de `public/`. O piloto suporta os discos locais `local` e `public`; S3 exige um adaptador assinado próprio antes de ser habilitado. Usar a mesma `SAAS_ASSET_KEY` aleatória (mínimo de 32 caracteres) em ambas. Credenciais não pertencem ao Git.
3. Definir `SAAS_TRUSTED_PROXIES` com os IPs/CIDRs reais do proxy. Usar HTTPS, cookies host-only, `APP_DEBUG=false`, rede privada para as APIs e DNS/TLS para o domínio-base e o host administrativo. O proxy preserva o Host e sobrescreve headers encaminhados. O exemplo Nginx não deve expor diretamente os diretórios `storage` das APIs.
4. Regenerar autoload e limpar caches de configuração/rotas nos artefatos novos. Não reutilizar workers da versão anterior.

```sh
composer install --no-interaction --prefer-dist
php artisan config:clear
php artisan route:clear
# Na API de autenticação, com SAAS_ENABLED=true:
php artisan migrate --database=saas_central --path=database/saas --force
php artisan saas:admin operador@empresa.example
```

A senha administrativa é solicitada no terminal. O painel fica em `https://<SAAS_PLATFORM_HOST>/plataforma` e usa tokens próprios, revogáveis no logout, com duração de oito horas. Nenhuma conta padrão é criada.

Compilar o frontend com `REACT_APP_SAAS_ENABLED=true`. As URLs passam a ser `/api/auth/v1` e `/api/estoque/v1`. O proxy também encaminha `/api/v1` para estoque para compatibilidade com links de exportação. `/storage` precisa ser reescrito para `/tenant-assets` e passar pelo Laravel; não pode servir o symlink legado. URLs de imagens usam assinaturas com validade de 15 minutos vinculadas ao host da empresa.

## Provisionamento e execução

No painel, cadastrar nome, slug, administrador inicial e módulos. Depois solicitar **Preparar ambiente**. Um provisionador supervisionado executa periodicamente:

```sh
php artisan saas:provision --pending --inventory-path=/srv/sierra/gerenciador_estoque_api
# Execução individual ou retomada após queda do processo:
php artisan saas:provision UUID --inventory-path=/srv/sierra/gerenciador_estoque_api
php artisan saas:provision UUID --resume --inventory-path=/srv/sierra/gerenciador_estoque_api
```

O provisionador precisa enxergar os dois projetos, suas dependências e configurações. Ele cria o banco de destino, executa migrations de autenticação e estoque em processos separados, aplica apenas dados obrigatórios e cria o administrador sem usuários de demonstração. Locks do MySQL impedem concorrência por cliente. Cada etapa é registrada em `saas_events`; falhas deixam o cliente indisponível. Não há exclusão automática do banco em uma falha. Logs de processo, quando coletados pelo operador, devem ter acesso restrito.

O administrador da empresa define o primeiro acesso usando **Esqueci minha senha** no subdomínio. Configurar e testar o transporte de e-mail em homologação; o provisionamento não envia mensagens automaticamente.

Cada worker atende uma única empresa; não alternar conexões entre clientes dentro do mesmo worker. Executar supervisores somente para clientes ativos:

```sh
php artisan saas:run UUID 'queue:work database --queue=documents,default --sleep=1 --tries=3 --timeout=180 --max-time=3600'
# Executar a cada minuto, por empresa ativa, na API operacional:
php artisan saas:run UUID 'schedule:run'
```

O payload da fila contém o cliente e é verificado antes da execução, junto com o estado atual e os módulos. Jobs desconhecidos são rejeitados até serem incluídos no catálogo. Tarefas agendadas propagam explicitamente o cliente aos subprocessos. Comandos de manutenção usam `saas:run`; o uso direto do banco operacional em modo SaaS falha sem contexto. Para manutenção de ambiente suspenso, usar `--provisioning` explicitamente e somente no terminal administrativo.

Para atualizar um cliente: suspender acesso, parar seus workers, registrar backup e versões dos três projetos, executar as migrations das duas APIs via `saas:run UUID 'migrate --force' --provisioning`, validar e reativar. Não usar `migrate:fresh` nem rollback de schema como estratégia automática de atualização.

## Integrações e dados da operação atual

`SAAS_INTEGRATIONS_FILE` aponta para um arquivo PHP de segredos montado somente nos servidores. Ele retorna um mapa por UUID com chaves `comms`, `conta_azul`, `google_calendar` e `banco_do_brasil`. Exemplo estrutural em `saas-integrations.example.php`. Configurações ausentes ficam sem credenciais; nunca herdam as credenciais da empresa original. O serviço externo de comunicação precisa fornecer um workspace/credenciais realmente isolados por cliente. Cadastrar nos provedores os callbacks HTTPS exatos de cada empresa. A criptografia de tokens permanece sob a chave do respectivo serviço; preservar a chave da API operacional ao adotar dados legados.

Para adotar a operação existente como primeiro cliente:

1. Cadastrar a empresa com todos os módulos e manter o registro pendente. Anotar o nome de banco gerado no registro central. Não executar o provisionamento padrão antes da importação do banco existente.
2. Em homologação, restaurar uma cópia consistente do banco atual **nesse banco de destino**, preservando a tabela `migrations`, IDs, perfis, tokens e tabelas operacionais. Nunca apontar um cliente de teste para o banco de produção.
3. Copiar os arquivos de `storage/app` das duas APIs para o diretório compartilhado `<SAAS_STORAGE_ROOT>/<UUID>/app`. Copiar também os uploads legados de `public/uploads` para `app/public/uploads` do cliente. Conferir hashes e resolver colisões de nomes; não sobrescrever arquivos divergentes automaticamente. Revisar URLs absolutas legadas e conexões de integrações.
4. Rodar `saas:provision UUID`. Ele executa migrations pendentes e cargas obrigatórias sem apagar dados. Usar como administrador inicial um e-mail administrativo existente: a senha e associações existentes são preservadas.
5. Comparar contagens de todas as tabelas, saldos por variação/depósito, reservas, pedidos, títulos e pagamentos, auditoria, permissões e hashes dos arquivos. Executar o ciclo completo de pedido, entrega, estorno, financeiro, assistência, comunicação e agenda com provedores de teste.
6. Ensaiar backup/restauração individual sem afetar um segundo cliente. Antes da virada real, pausar escritas e workers legados e repetir backup, restauração e comparação. Manter o legado sem escrita.
7. Validar antes de liberar usuários. Antes de novas escritas, é possível retornar ao legado preservado. Depois de novas escritas, o retorno exige reconciliação; restaurar uma cópia antiga perderia operações.

## Verificação e limites atuais

```sh
# Em cada API: os testes SaaS usam SQLite temporário, sem migrar banco existente.
php vendor/bin/phpunit --filter Saas tests/Feature
# No frontend:
npm test -- --watchAll=false --runInBand --silent
npm run build
```

Validação local em 07/09/2026: 49 testes funcionais da autenticação (208 assertions), 821 testes funcionais da API operacional (4.782 assertions) e 90 testes unitários operacionais (398 assertions) passaram. As suítes incluem isolamento de banco, sessão, cache, arquivos, filas, módulos e administração da plataforma. O PHP do WSL usou GD extraído localmente apenas para a execução dos testes, sem alterar a instalação global.

O frontend passou em 132 suites (840 testes), no build de producao SaaS e no teste Playwright do painel administrativo em desktop e mobile. A verificacao de sintaxe cobriu 851 arquivos PHP sem falhas. O build ainda apresenta avisos de lint preexistentes.

O ensaio com MySQL 8.4 descartável provisionou duas empresas fictícias com 177 migrations e um administrador cada. Repetir o provisionamento não duplicou dados. O backup de uma empresa foi restaurado em outro banco: contagens e checksums das 129 tabelas conferiram, sem modificar a segunda empresa. Os bancos de teste não contêm dados reais da operação.

Antes de ativar em produção, validar em homologação os grants reais, workers supervisionados, proxy/TLS, e-mail, provedores externos e a migração dos dados e arquivos da empresa atual. O ensaio com dados sintéticos não substitui essa validação. A migração da operação atual e o deploy não foram executados. Cobrança, checkout, limites comerciais e domínios personalizados ficam fora deste piloto.
