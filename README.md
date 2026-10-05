# Gerenciador de Projetos e Tarefas

Aplicação interna para organizar projetos, participantes, tarefas, subtarefas, comentários e arquivos anexados. O projeto tem finalidade de estudo: demonstra recursos do Laravel, Eloquent, Filament, Policies e permissões por perfil.

## Tecnologias

- PHP 8.3 ou superior e Laravel 13
- Filament 4 para o painel administrativo
- PostgreSQL 16
- Eloquent ORM
- Filament Shield e Spatie Laravel Permission para roles e permissões
- Spatie Media Library para anexos
- Vite, Node.js e npm para os recursos de frontend
- Docker Compose para executar o PostgreSQL localmente

## Requisitos

- PHP 8.3+ com extensões necessárias pelo Laravel, incluindo `pdo_pgsql`
- Composer
- Node.js e npm
- Docker Engine com Docker Compose, ou um servidor PostgreSQL 16 acessível

Confira a conexão do PostgreSQL no `.env`. O arquivo `.env.example` já usa o banco local deste Compose:

| Configuração | Valor padrão |
| --- | --- |
| Banco | `gerenciador` |
| Host e porta | `127.0.0.1:5432` |
| Usuário | `gerenciador` |
| Senha | `gerenciador` |

## Instalação e execução local

Execute os comandos a partir da pasta raiz do projeto.

1. Inicie o PostgreSQL:

   ```bash
   docker compose up -d postgres
   docker compose ps
   ```

   Aguarde o serviço `gerenciador-postgres` ficar `healthy`.

2. Instale as dependências PHP e JavaScript:

   ```bash
   composer install
   npm install
   ```

3. Crie o arquivo de ambiente, se ainda não existir, e gere a chave da aplicação:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Se o `.env` já existir, não o sobrescreva com o exemplo. Confira apenas as variáveis de conexão do PostgreSQL e mantenha sua chave atual.

4. Crie as tabelas, permissões e dados demonstrativos:

   ```bash
   php artisan migrate --seed
   ```

5. Crie o link público de armazenamento para os arquivos enviados:

   ```bash
   php artisan storage:link
   ```

6. Inicie o servidor Laravel, a fila, os logs e o Vite:

   ```bash
   composer run dev
   ```

7. Abra o painel Filament em [http://localhost:8000/admin](http://localhost:8000/admin).

O comando `composer run dev` mantém os processos em execução no terminal. Use `Ctrl+C` para encerrá-los. Se preferir compilar os recursos uma vez em vez de manter o Vite ativo, rode `npm run build` e inicie o servidor Laravel separadamente com `php artisan serve`.

### Atalho de instalação

Com um PostgreSQL já iniciado e o arquivo `.env` corretamente configurado, o script `composer run setup` automatiza instalação de dependências, chave (se aplicável), migrations e build do frontend. Em ambientes existentes, confira o `.env` antes de usar o atalho.

## Contas de demonstração

O seeder cria três contas locais:

| Perfil | E-mail |
| --- | --- |
| Administrador | `admin@example.com` |
| Gestor | `gestor@example.com` |
| Membro | `membro@example.com` |

A senha das três contas é `password`.

> **Atenção:** essas credenciais são exclusivamente para desenvolvimento e demonstração. Troque-as ou remova essas contas antes de qualquer uso fora de um ambiente local.

Para recriar/atualizar os dados de exemplo sem apagar o banco:

```bash
php artisan db:seed
```

O seeder prepara os papéis e as permissões do Shield, os usuários, um projeto, tarefas em diferentes estados e algumas subtarefas. Ele usa `updateOrCreate`/`firstOrCreate` para poder ser executado novamente.

## Como usar

1. Entre no painel com uma das contas de demonstração.
2. Em **Projetos**, consulte o andamento e o progresso calculado. Gestores podem administrar os próprios projetos e associar membros.
3. Em **Tarefas**, crie tarefas em projetos abertos, escolha um responsável que participe do projeto e defina prioridade e prazo.
4. Abra uma tarefa para consultar subtarefas, comentários e anexos. Participantes autorizados podem comentar e baixar anexos; gestores do projeto podem administrar a tarefa e os arquivos.
5. Use **Minhas tarefas** para filtrar as tarefas atribuídas ao usuário por todas, a fazer, em andamento, em revisão, atrasadas e concluídas.
6. No dashboard, confira indicadores, a distribuição das tarefas por status e os próximos vencimentos.
7. Use a área de **Shield / Roles** para revisar papéis e permissões. O código também aplica Policies e regras de negócio, portanto permissões de navegação não substituem as verificações de acesso por projeto.

### Perfis

- **Administrador:** acesso administrativo amplo a usuários, projetos, tarefas e permissões.
- **Gestor:** administra projetos sob sua responsabilidade, seus membros e tarefas.
- **Membro:** vê projetos dos quais participa e tarefas atribuídas a si; pode alterar o status das próprias tarefas e comentar.

## Regras de negócio importantes

- Cada tarefa pertence a um projeto; o responsável precisa ser membro do projeto.
- Projetos concluídos ou cancelados não recebem novas tarefas.
- Datas de início e prazo das tarefas respeitam o período do projeto.
- Uma tarefa vencida e ainda aberta é identificada como atrasada por consulta, sem um status `overdue` no banco.
- `completed_at` é preenchido ao concluir uma tarefa e limpo ao reabri-la.
- Um projeto só pode ser concluído quando suas tarefas estiverem concluídas ou canceladas; a conclusão é uma ação explícita.
- O progresso do projeto é calculado a partir das tarefas e não é editado manualmente.
- Notificações internas do Filament avisam sobre atribuições e conclusões de tarefas. Não há envio externo de e-mail ou mensagens.

## Estrutura de dados

As migrations criam, entre outras, as tabelas `users`, `projects`, `project_user`, `tasks`, `subtasks`, `task_comments`, `media`, `notifications` e as tabelas de roles e permissions do Spatie Permission/Shield. `project_user` implementa a relação muitos-para-muitos entre projetos e usuários; tarefas têm relações para projeto, responsável, criador, subtarefas, comentários e arquivos.

## Testes e qualidade

Execute os testes automatizados:

```bash
php artisan test --compact
```

Formate os arquivos PHP com Pint:

```bash
vendor/bin/pint --format agent
```

A configuração de testes usa SQLite em memória; a aplicação em desenvolvimento usa PostgreSQL conforme o `.env`.

## Solução de problemas

- **`npm run dev` retorna `127` ou `vite: not found`:** execute `npm install` na raiz do projeto e tente novamente.
- **Erro de conexão com PostgreSQL:** verifique `docker compose ps`, aguarde o healthcheck e confira host, porta, banco e credenciais no `.env`.
- **Tabelas ausentes:** execute `php artisan migrate --seed`.
- **Arquivos não aparecem em URLs locais:** verifique `FILESYSTEM_DISK=public` no `.env` e execute `php artisan storage:link`.
- **Configuração antiga em cache:** execute `php artisan optimize:clear`.

Para parar o banco sem remover os dados persistidos no volume:

```bash
docker compose stop postgres
```

> `docker compose down -v` também remove o volume do PostgreSQL e apaga os dados locais; use somente se quiser realmente reiniciar o banco do zero.

## Git

Use commits pequenos e descritivos, por exemplo:

```text
feat: adicionar filtro de tarefas atrasadas
fix: corrigir validação de prazo
```
