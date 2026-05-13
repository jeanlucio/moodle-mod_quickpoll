# Moodle Activity Purpose (Cores de Ícone)

Desde o Moodle 4.0, o sistema de **Activity Purpose** (`FEATURE_MOD_PURPOSE`) faz o
tema colorir automaticamente o fundo do ícone da atividade conforme sua categoria
pedagógica.

## Constantes disponíveis

| Constante | Cor (Boost) | Uso típico |
|---|---|---|
| `MOD_PURPOSE_ASSESSMENT` | vermelho | Avaliação (quiz, assign) |
| `MOD_PURPOSE_COLLABORATION` | verde | Colaboração (wiki, forum) |
| `MOD_PURPOSE_COMMUNICATION` | azul | Comunicação (chat) |
| `MOD_PURPOSE_CONTENT` | laranja | Conteúdo (page, book, resource) |
| `MOD_PURPOSE_INTERACTIVECONTENT` | roxo | Conteúdo interativo (h5p, scorm) |
| `MOD_PURPOSE_INTERFACE` | cinza | Interface/utilitário (label) |
| `MOD_PURPOSE_ADMINISTRATION` | cinza escuro | Administração |

## Como declarar no plugin

Em `lib.php`, dentro da função `MODNAME_supports()`:

```php
case FEATURE_MOD_PURPOSE:
    return MOD_PURPOSE_COMMUNICATION; // ajuste conforme o uso pedagógico
```

## Recomendação para mod_quickpoll

O `quickpoll` é uma enquete/votação em tempo real. As opções mais adequadas são:

- `MOD_PURPOSE_COMMUNICATION` — se o foco é interação e diálogo em aula
- `MOD_PURPOSE_ASSESSMENT` — se o foco é verificação de aprendizagem

O plugin declara `MOD_PURPOSE_COMMUNICATION` em `lib.php` (ícone azul no tema Boost).
