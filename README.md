# 🔐 API de Autenticação - Sistema ERP/CRM

Esta API é responsável pelo gerenciamento de usuários, autenticação via JWT, controle de permissões (RBAC) e gestão de sessões no sistema ERP/CRM.

## 🚀 Tecnologias Utilizadas

* [Laravel 10+](https://laravel.com/)
* [PHP 8.2+](https://www.php.net/)
* [Sanctum](https://laravel.com/docs/sanctum)
* [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/)
* [PostgreSQL](https://www.postgresql.org/)
* [Docker](https://www.docker.com/) (opcional)

## 📁 Estrutura de Pastas Relevante

```sh
app/
├── Http/
│   ├── Controllers/       # AuthController, UserController
├── Models/                # User.php, Role.php, Permission.php
├── Providers/             # AuthServiceProvider
routes/
└── api.php                # Rotas da API
```

## ⚙️ Instalação

1. Clone o repositório e acesse o diretório:

```bash
git clone https://github.com/seu-usuario/backend-auth.git
cd backend-auth
```

2. Instale as dependências:

```bash
composer install
```

3. Copie o `.env.example` e configure:

```bash
cp .env.example .env
php artisan key:generate
```

4. Configure o banco de dados e execute as migrations:

```bash
php artisan migrate --seed
```

5. Inicie o servidor:

```bash
php artisan serve
```

## 🔑 Autenticação e Permissões

* Autenticação via Sanctum com JWT (Token armazenado em cookie seguro ou no front).
* Controle de acesso baseado em permissões e papéis (RBAC) com a biblioteca Spatie.

### Endpoints Principais

| Método | Rota             | Descrição                       |
| ------ | ---------------- | ------------------------------- |
| POST   | /api/login       | Login com e-mail e senha        |
| POST   | /api/register    | Registro de novo usuário        |
| GET    | /api/user        | Dados do usuário autenticado    |
| POST   | /api/logout      | Logout                          |
| GET    | /api/permissions | Lista de permissões disponíveis |
| GET    | /api/roles       | Lista de papéis                 |

## 🧪 Testes

Você pode usar o Insomnia ou Postman para testar os endpoints.

## 🔧 Variáveis de Ambiente

```env
APP_NAME=ERPAuth
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=erp_auth
DB_USERNAME=root
DB_PASSWORD=secret
```

