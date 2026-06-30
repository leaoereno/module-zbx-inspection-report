# Inspection report (fork)

> Pacote: `module-zbx-inspection-report`

Módulo de frontend para **Zabbix 7.0 LTS** que gera um relatório de inspeção/saúde dos componentes do Zabbix (server, proxy e banco MySQL/MariaDB), comparando dezenas de métricas — caches, processos internos, NVPS, fila de pré-processamento, recursos de SO e replicação MySQL — contra limiares de referência e produzindo uma análise com diagnóstico e sugestão para cada item fora do esperado.

> **Esta é uma versão fork.** É um fork do módulo *Inspection report* original, de autoria de **thinkc** (`thinkc@outlook.com`), com correções de segurança, de bugs, de robustez e suporte a server/proxy em Docker. Veja o [Histórico de mudanças](#histórico-de-mudanças-deste-fork).

## Instalação

### 1. Chaves de agente no Zabbix server

Crie um arquivo `.conf` no diretório de includes do agente (ou direto no `zabbix_agentd.conf`) com as duas chaves abaixo. A leitura do `.conf` é **filtrada para não expor segredos** (senha do banco, tokens, material TLS):

```
UserParameter=zabbix.server.conf,grep -Ev "^$|^#" /etc/zabbix/zabbix_server.conf | grep -Eiv "Password|DBUser|TLSPSK|VaultToken|VaultDBPath|DBTLSKeyFile|DBTLSCertFile"
UserParameter=zabbix.server.log,grep -Ei "exit|stop|fail" /var/log/zabbix/zabbix_server.log | tail -n 20
```

### 2. Chaves de agente no Zabbix proxy

```
UserParameter=zabbix.proxy.conf,grep -Ev "^$|^#" /etc/zabbix/zabbix_proxy.conf | grep -Eiv "Password|DBUser|TLSPSK|VaultToken|VaultDBPath|DBTLSKeyFile|DBTLSCertFile"
UserParameter=zabbix.proxy.log,grep -Ei "exit|stop|fail" /var/log/zabbix/zabbix_proxy.log | tail -n 20
```

> Mesmo com o filtro acima, o módulo aplica um mascaramento defensivo no servidor (`scrubSecrets()`) antes de renderizar a configuração, cobrindo valores que já estejam no histórico de coletas anteriores. Ainda assim, **revise o filtro conforme as diretivas sensíveis do seu ambiente** (ex.: `Vault*`, `*Password`, chaves TLS) antes de habilitar a coleta.

### 3. Ambientes em Docker / containers (server e proxies containerizados)

O módulo detecta automaticamente quando o Zabbix server, proxy ou banco roda em container e **adapta a coleta**: em container não há `zabbix_server.conf` plano (a configuração vem de variáveis `ZBX_*`) e o log vai para o *stdout* (`docker logs`). A análise numérica (caches, processos internos, NVPS, fila) é lida de itens internos via API e **funciona igual**, independente de container.

A detecção é robusta nos dois modelos de deploy, sem configuração obrigatória:

- **Agente dentro do container** (sidecar/imagem combinada): adicione a chave de runtime abaixo — ela retorna `docker`/`bare` lendo `/.dockerenv`, `/run/.containerenv` e `/proc/1/cgroup`.
- **Agente no host monitorando via socket** (template *Docker by Zabbix agent 2*): nada a fazer — o módulo identifica o container pela presença de itens `docker.*` ou do template Docker no host (fallback automático).

Chaves opcionais (quando o agente roda **dentro** do container do server/proxy):

```
# runtime: retorna "docker" ou "bare"
UserParameter=zabbix.server.runtime,if [ -f /.dockerenv ] || [ -f /run/.containerenv ] || grep -qaE 'docker|containerd|kubepods|libpod|podman' /proc/1/cgroup 2>/dev/null; then echo docker; else echo bare; fi
# env: variáveis de tuning ZBX_*, já filtrando segredos na origem
UserParameter=zabbix.server.env,env | grep -E '^ZBX_' | grep -Eiv 'PASSWORD|TLSPSK|VAULT|TOKEN|SECRET'
```

Equivalentes para proxy (`zabbix.proxy.runtime`, `zabbix.proxy.env`) e, se o banco também for containerizado, `zabbix.database.runtime`. Crie os itens correspondentes na UI: `*.runtime` como tipo de informação *Character*, `*.env` como *Text*.

Quando o runtime é `docker` e a env é coletada, o bloco *Configuration information* passa a mostrar a config derivada das variáveis `ZBX_*` (com o rótulo "Source: container environment") em vez do arquivo. Cada host containerizado também gera uma linha na *Analysis of inspection results* (tipo `docker`) com as recomendações operacionais do caso (limites de recurso, restart policy, logs via driver do container).

### 4. Itens de monitoramento

Adicione os itens de coleta (`zabbix.server.conf`, `zabbix.server.log`, `zabbix.proxy.conf`, `zabbix.proxy.log` e, para Docker, `*.runtime`/`*.env`) aos hosts de server e proxy na UI. Use o tipo *Zabbix agent* e o tipo de informação indicado em cada caso.

### 5. Deploy do módulo

Envie o diretório do módulo para a pasta de módulos da UI. Em instalações via pacote (apt/yum), o caminho padrão é:

```
/usr/share/zabbix/modules/
```

Descompacte e garanta que o diretório se chame `module-zbx-inspection-report` e esteja no local correto. Em AlmaLinux/RHEL, ajuste o dono:

```bash
chown -R apache:apache /usr/share/zabbix/modules/module-zbx-inspection-report
```

### 6. Habilitar

Logue como administrador e vá em **Administration → General → Modules**, clique em **Scan directory**, localize *Inspection report* e habilite no botão à direita.

### 7. Gerar o relatório

No menu **Reports → Inspection report**, selecione o(s) server(s), proxy(s) e banco(s) a inspecionar, escolha o trimestre (*Inspection cycle*) e clique em **Generate**.

## Notas de segurança

- A coleta do arquivo de configuração **nunca deve incluir senhas, tokens ou chaves**. Os `UserParameter` acima já filtram as diretivas mais comuns; valide-os contra o seu `zabbix_server.conf`/`zabbix_proxy.conf`.
- O acesso ao relatório é controlado pela permissão de UI **Reports → Scheduled reports**.
- O módulo não executa comandos de shell no frontend; toda a coleta vem de itens de agente.

## Histórico de mudanças deste fork

- **Segurança:** UserParameters de `.conf` filtram segredos; mascaramento defensivo (`scrubSecrets()`) das diretivas sensíveis antes da renderização.
- **Bug:** objeto `CPre` agora é criado por linha (antes era compartilhado entre todas as linhas, concatenando logs/configs de todos os hosts).
- **Bug:** `dstfrm` dos multiselects corrigido para `inspectionReportForm` (o popup de seleção agora grava no formulário correto).
- **Compatibilidade:** `manifest_version` ajustado para o inteiro `2`.
- **Robustez:** redirecionamentos de seleção inválida movidos para o controller via `CControllerResponseRedirect` (sem `header()`/`http://` fixos na view; compatível com SSL offload / F5).
- **Robustez:** acessos a resultados de API protegidos com null-safety (`?? `) em todo o controller — elimina os avisos PHP 8 "Undefined array key 0" / "Trying to access array offset on null" quando um host não tem o item esperado.
- **Refatoração:** lógica de trend por trimestre extraída para `getTrendMaxForCycle()`; blocos repetidos de ciclo colapsados (controller reduzido de ~6.5k para ~3.5k linhas).
- **Docker:** detecção automática de server/proxy/banco em container (`detectDocker()` via item `*.runtime`, itens `docker.*`/template Docker, ou hints em uname/os); leitura da config via variáveis `ZBX_*` quando containerizado; coluna *Runtime* no relatório, rótulo de origem da config e linha de análise dedicada (tipo `docker`).

> Pendência conhecida: o array `$result_analysis` é reaproveitado entre verificações no controller; cada bloco preenche todos os campos, mas vale reinicializá-lo por verificação para evitar herança acidental de valores no futuro.

## Compatibilidade

- Zabbix 7.0 LTS
- PHP 8.x

## Licença

MIT

## Créditos

Este projeto é um **fork**. Os créditos pela criação original do módulo pertencem ao autor upstream; este fork apenas o mantém e estende.

- **Autor original:** thinkc (`thinkc@outlook.com`) — módulo *Inspection report*
- **Mantenedor do fork:** Rafael M. A. Leão Ereno (MALE)
- **LinkedIn:** https://www.linkedin.com/in/leaoereno/
