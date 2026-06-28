# Preview Pipeline

## Papel do Preview

O Preview de migracao e a etapa final antes da geracao do Bundle `.schf`. Ele consolida os resultados de Inventory, Normalization e DataQuality em um relatorio que responde:

- O que sera migrado
- O que sera ignorado
- O que ficara em historico
- O que possui erro
- O que possui alerta
- Se o projeto esta pronto para gerar o Bundle

**Importante:** O Preview **nao importa dados reais** no SCHF Core. Ele apenas analisa e apresenta.

---

## Diferenca entre Warning e Error

| Severidade | Descricao | Bloqueia Bundle? |
|------------|-----------|-------------------|
| `warning`  | Problema que merece atencao mas nao impede a migracao | Nao |
| `error`    | Problema critico que impede a importacao de um registro | Sim |

### Exemplos de Warning
- Data com formato inesperado (`invalid_date`)
- Valor negativo em campo monetario (`negative_value`)
- Referencia orfa para fornecedor/categoria (`orphan`)

### Exemplos de Error
- Nome vazio em entidade que requer nome (`empty_name`)
- `external_id` duplicado (`duplicate`)
- Campo obrigatorio ausente (`missing_required`)

---

## Regra de `ready_for_bundle`

`ready_for_bundle = true` somente se **TODAS** as condicoes forem atendidas:

1. Nao ha errors (warnings sao aceitos)
2. `source_config` existe no projeto
3. Inventario existe em `source_config.inventory`
4. Estrutura detectada existe em `source_config.detected_structure`
5. Ha pelo menos 1 registro normalizado (suppliers + payables + categories > 0)

Se qualquer condicao falhar, `ready_for_bundle = false` e `status = 'blocked'`.

---

## Formato JSON

Resposta completa do endpoint de preview:

```json
{
  "project_id": 1,
  "status": "ready",
  "ready_for_bundle": true,
  "summary": {
    "total_records": 150,
    "valid_records": 140,
    "warning_count": 5,
    "error_count": 0,
    "ignored_count": 3,
    "historical_count": 2
  },
  "entities": {
    "suppliers": {
      "total": 50,
      "valid": 48,
      "warnings": 2,
      "errors": 0,
      "ignored": 1,
      "historical": 0
    },
    "payables": {
      "total": 80,
      "valid": 75,
      "warnings": 3,
      "errors": 0,
      "ignored": 2,
      "historical": 2
    },
    "categories": {
      "total": 20,
      "valid": 17,
      "warnings": 0,
      "errors": 0,
      "ignored": 0,
      "historical": 0
    }
  },
  "warnings": [
    {
      "type": "orphan",
      "entity": "payables",
      "external_id": "P001",
      "field": "supplier_external_id",
      "message": "References unknown supplier 'S999'",
      "value": "S999"
    }
  ],
  "errors": [],
  "ignored": [
    {
      "entity": "suppliers",
      "external_id": "S005",
      "reason": "Duplicate"
    }
  ],
  "historical": [
    {
      "entity": "payables",
      "external_id": "P042",
      "reason": "Record predates data cutoff"
    }
  ],
  "generated_at": "2026-06-28T12:00:00+00:00"
}
```

---

## Fluxo API

### Gerar Preview

```http
POST /api/projects/{id}/preview/generate
```

Fluxo interno:

```
Load Project
    |
    v
Load Inventory (via Connector)
    |
    v
Run Normalization (NormalizationService)
    |
    v
Run DataQuality (DataQualityService)
    |
    v
Generate Preview (MigrationPreviewService)
    |
    v
Persist Preview (MigrationPreview model)
    |
    v
Return JSON
```

Resposta: `200 OK` com o JSON do preview.
Resposta de erro: `422 Unprocessable Entity` se o projeto nao tem `source_config`.

### Obter Ultimo Preview

```http
GET /api/projects/{id}/preview/result
```

Retorna o preview mais recente gerado para o projeto.
Resposta: `200 OK` ou `404 Not Found` se nenhum preview foi gerado.

---

## Exemplos de Resposta

### Projeto Pronto

```json
{
  "project_id": 1,
  "status": "ready",
  "ready_for_bundle": true,
  "summary": { "total_records": 100, "valid_records": 95, "warning_count": 5, "error_count": 0, "ignored_count": 0, "historical_count": 0 },
  "entities": { ... },
  "warnings": [ ... ],
  "errors": [],
  "ignored": [],
  "historical": [],
  "generated_at": "2026-06-28T12:00:00+00:00"
}
```

### Projeto Bloqueado

```json
{
  "project_id": 1,
  "status": "blocked",
  "ready_for_bundle": false,
  "summary": { "total_records": 100, "valid_records": 80, "warning_count": 2, "error_count": 3, "ignored_count": 0, "historical_count": 0 },
  "entities": { ... },
  "warnings": [ ... ],
  "errors": [
    { "type": "empty_name", "entity": "suppliers", "external_id": "S003", "field": "name", "message": "Entity has empty name", "value": null }
  ],
  "ignored": [],
  "historical": [],
  "generated_at": "2026-06-28T12:00:00+00:00"
}
```

---

## Como a UI Deve Interpretar o Resultado

1. **Badge Ready/Blocked**: Se `ready_for_bundle = true`, exibir badge verde "Ready for Bundle". Se `false`, exibir badge vermelho "Blocked".

2. **Cards de Resumo**: Os 6 campos do `summary` devem ser exibidos como cards numericos com cores:
   - `total_records`: cinza
   - `valid_records`: verde
   - `warning_count`: amarelo
   - `error_count`: vermelho
   - `ignored_count`: cinza claro
   - `historical_count`: azul

3. **Tabela de Entidades**: Uma linha por entidade em `entities`, com as mesmas colunas do resumo.

4. **Listas de Issues**: Seccoes separadas para warnings (amarelo), errors (vermelho), ignored (cinza) e historical (azul). Cada item mostra tipo, entidade, campo e mensagem.

5. **Bloqueio Visual**: Se `ready_for_bundle = false`, desabilitar o botao "Export Bundle" e exibir mensagem explicando que errors precisam ser corrigidos.
