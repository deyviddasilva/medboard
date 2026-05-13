# 🩺 MedBoard — Painel de Gestão Médica

Sistema web desenvolvido para auxiliar médicos na organização
da rotina profissional, com controle de agenda, locais de trabalho,
registro de atendimentos e relatórios de produtividade.

---

## 📋 Funcionalidades

- 🔐 **Autenticação** — login seguro com senha criptografada (bcrypt)
- 🏠 **Dashboard** — visão geral do dia, semana e próximos plantões
- 📅 **Agenda mensal** — calendário com turnos por local e horário
- 📍 **Locais de trabalho** — cadastro com cor de identificação
- 📝 **Registro do dia** — lançamento de pacientes, faltas, encaixes e tempo médio
- 👥 **Atendimentos** — histórico completo com filtros por local e turno
- 📊 **Relatórios** — gráficos de evolução, produtividade e taxa de faltas

---

## 🛠 Tecnologias utilizadas

| Camada | Tecnologia |
|---|---|
| Frontend | HTML5, CSS3 (puro), JavaScript (vanilla) |
| Backend | PHP 8+ |
| Banco de dados | MySQL 8 + PDO |
| Servidor local | XAMPP / WAMP |
| Versionamento | Git + GitHub |

---

## 📁 Estrutura do projeto
sistema_medico/
│
├── config/
│   ├── database.php          ← credenciais locais (não versionado)
│   └── database.example.php  ← modelo de configuração
│
├── includes/
│   ├── header.php
│   ├── sidebar.php
│   └── footer.php
│
├── assets/
│   ├── css/style.css
│   ├── js/main.js
│   └── img/
│
├── pages/
│   ├── agenda.php
│   ├── registro_dia.php
│   ├── atendimentos.php
│   ├── locais.php
│   └── relatorios.php
│
├── auth/
│   ├── login.php
│   └── logout.php
│
├── database/
│   └── schema.sql
│
├── index.php
├── .gitignore
└── README.md

---

## ⚙️ Como rodar localmente

### Pré-requisitos

- PHP 8.0 ou superior
- MySQL 8.0 ou superior
- XAMPP ou WAMP

### Instalação

**1. Clone o repositório**
```bash
git clone https://github.com/seu-usuario/medboard.git
```

**2. Configure o banco de dados**

Abra o phpMyAdmin e execute o arquivo:
database/schema.sql

**3. Configure a conexão**

Copie o arquivo de exemplo e preencha com suas credenciais:
```bash
cp config/database.example.php config/database.php
```

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_medico');
define('DB_USER', 'root');
define('DB_PASS', '');
```

**4. Crie o usuário inicial**

Execute no phpMyAdmin:
```sql
INSERT INTO usuarios (nome, email, senha, idioma) VALUES
('Dra. Seu Nome', 'seu@email.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'pt-BR');
```
> Senha padrão: `password` — troque após o primeiro acesso.

**5. Acesse no navegador**
http://localhost/medboard/auth/login.php

---

## 🔒 Segurança

- Senhas armazenadas com `password_hash()` bcrypt
- Queries com PDO e prepared statements
- Proteção contra XSS com `htmlspecialchars()`
- Sessões PHP para controle de autenticação
- Arquivo de configuração fora do versionamento

---

## 🗺 Roadmap — V2

- [ ] Página de configurações (editar nome, email e senha)
- [ ] Cadastro e gestão de lembretes
- [ ] Exportação de relatórios em PDF
- [ ] Layout responsivo para dispositivos móveis
- [ ] Modo escuro
- [ ] Edição de turnos na agenda
- [ ] Recuperação de senha por e-mail

---

## 👨‍💻 Autor

Desenvolvido por **[Deyvid GABRIEL]**
para uso pessoal da **Dra.**

---

## 📄 Licença

Este projeto é de uso privado e pessoal.