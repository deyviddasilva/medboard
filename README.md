# 🩺 MedBoard — Painel de Gestão Médica

Sistema web desenvolvido para auxiliar médicos na organização
da rotina profissional, com controle de agenda, locais de trabalho,
registro de atendimentos e relatórios de produtividade.

---

## 📋 Funcionalidades

### 🔐 Autenticação e Segurança
- Login seguro com senha criptografada (bcrypt)
- Proteção CSRF em todos os formulários
- Rate limiting — bloqueio após 5 tentativas erradas
- Timeout de sessão por inatividade (2 horas)
- Mostrar/ocultar senha no login

### 🏠 Dashboard
- Visão geral do dia e semana
- KPIs em tempo real (atendidos, faltas, encaixes)
- Aviso de turnos sem registro lançado
- Lembretes do dia em destaque
- Gráfico de atendimentos da semana
- Próximos plantões com dia da semana

### 📅 Agenda Mensal
- Calendário visual com turnos por cor de local
- Cadastro, edição e exclusão de turnos
- Suporte a turnos noturnos que viram o dia
- Navegação entre meses
- Legenda de locais

### 📍 Locais de Trabalho
- Cadastro com cor de identificação
- Edição e exclusão de locais
- Ativar/desativar locais
- Exibição no calendário e agenda

### 📝 Registro do Dia
- Lançamento de pacientes atendidos, faltas, cancelamentos e encaixes
- Tempo médio por consulta
- Horário real de início e fim
- Edição de registros já lançados
- Situação do dia (realizado, parcialmente, não realizado, substituído)

### 👥 Atendimentos
- Histórico completo com filtros por local e turno
- Navegação entre meses
- KPIs do período filtrado
- Paginação (10 registros por página)

### 📊 Relatórios
- Gráfico de atendimentos por dia do mês
- Comparativo por local de trabalho
- Atendimentos por dia da semana
- Evolução dos últimos 6 meses
- Taxa de faltas e médias

### 🔔 Lembretes
- Cadastro com título, data, hora e descrição
- Destaque visual para lembretes de hoje e atrasados
- Marcar como concluído
- Histórico de concluídos recentes

### ⚙️ Configurações
- Edição de nome e e-mail
- Alteração de senha com validação
- Informações do sistema

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
│   ├── footer.php
│   └── auth_check.php        ← verificação de sessão e timeout
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
│   ├── relatorios.php
│   ├── lembretes.php
│   └── configuracoes.php
│
├── auth/
│   ├── login.php
│   └── logout.php
│
├── database/
│   └── schema.sql            ← estrutura completa do banco
│
├── index.php                 ← Home / Dashboard
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
> Senha padrão: `password` — troque após o primeiro acesso em Configurações.

**5. Acesse no navegador**
http://localhost/medboard/auth/login.php

---

## 🔒 Segurança

- Senhas armazenadas com `password_hash()` bcrypt
- Queries com PDO e prepared statements
- Proteção contra XSS com `htmlspecialchars()`
- Proteção CSRF em todos os formulários POST
- Rate limiting no login (bloqueio após 5 tentativas)
- Timeout de sessão por inatividade (2 horas)
- Arquivo de configuração fora do versionamento

---

## 🗺 Roadmap

### ✅ V1 — Base do sistema
- [x] Banco de dados modelado
- [x] Login seguro
- [x] Dashboard com dados reais
- [x] Agenda mensal com calendário
- [x] Registro de atendimentos
- [x] Histórico com filtros
- [x] Relatórios com gráficos

### ✅ V2 — Segurança e funcionalidades
- [x] Proteção CSRF
- [x] Rate limiting e timeout de sessão
- [x] Edição de agenda, registros e locais
- [x] Página de configurações
- [x] Lembretes
- [x] Paginação

### 🔜 V3 — Design e experiência
- [ ] Layout responsivo para celular
- [ ] Design profissional e animações
- [ ] Modo escuro
- [ ] Gráficos interativos com Chart.js
- [ ] Exportar relatórios em PDF
- [ ] Recuperação de senha por e-mail

---

## 👨‍💻 Autor

Desenvolvido por **[ Deyvid Gabriel ]**
para uso pessoal da **Dra. Melody**

---

## 📄 Licença

Este projeto é de uso privado e pessoal.