-- ============================================================================
-- RELATÓRIOS SQL - ESTOQUE PRO
-- Teste prático de programação Júnior - César Danilo Palácios Ortega
-- ============================================================================
--
-- Schema: public
-- User ID: 01a0160c-640c-70ff-b265-76af4b0fca63
--
-- OBSERVAÇÃO SOBRE MODELAGEM:
-- - O modelo atual não tem uma tabela de "subgrupo" nem coluna "marca".
--   Para os relatórios de produto por grupo/subgrupo e por marca, foi usado o
--   próprio "group" como grupo, e o fornecedor (people.name onde
--   category = 'supplier') como "marca/fabricante" do produto (products.supplier_id).
-- ============================================================================


-- ============================================================================
-- A. RELATÓRIOS DE PESSOAS
-- ============================================================================

-- ----------------------------------------------------------------------------
-- A1. Todas as pessoas
-- ----------------------------------------------------------------------------
SELECT
    id,
    name,
    trade_name,
    document,
    category,
    type,
    gender,
    birth_date,
    phone,
    email,
    city,
    state,
    active,
    created_at
FROM public.people
WHERE user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
ORDER BY name ASC;


-- ----------------------------------------------------------------------------
-- A2. Todas as pessoas por grupo (categoria: cliente / fornecedor)
-- ----------------------------------------------------------------------------
SELECT
    category AS grupo,
    COUNT(*) AS total_pessoas
FROM public.people
WHERE user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
GROUP BY category
ORDER BY total_pessoas DESC;


-- ----------------------------------------------------------------------------
-- A3. Todas as pessoas por gênero (homens ou mulheres)
-- ----------------------------------------------------------------------------
SELECT
    COALESCE(gender, 'nao_informado') AS genero,
    COUNT(*) AS total_pessoas
FROM public.people
WHERE user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
GROUP BY COALESCE(gender, 'nao_informado')
ORDER BY total_pessoas DESC;


-- ----------------------------------------------------------------------------
-- A4. Todas as pessoas agrupadas por faixa etária
-- ----------------------------------------------------------------------------
SELECT
    CASE
        WHEN birth_date IS NULL THEN 'nao_informado'
        WHEN EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN 18 AND 25 THEN '18_a_25'
        WHEN EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN 26 AND 35 THEN '26_a_35'
        WHEN EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN 36 AND 45 THEN '36_a_45'
        WHEN EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN 46 AND 60 THEN '46_a_60'
        ELSE 'acima_de_60'
    END AS faixa_etaria,
    COUNT(*) AS total_pessoas
FROM public.people
WHERE user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
GROUP BY faixa_etaria
ORDER BY faixa_etaria;


-- ============================================================================
-- B. RELATÓRIOS DE PRODUTOS
-- ============================================================================

-- ----------------------------------------------------------------------------
-- B1. Todos os produtos de um determinado grupo
-- ----------------------------------------------------------------------------
SELECT
    p.id,
    p.name,
    g.name AS grupo,
    p.sale_price,
    p.stock_quantity,
    p.active
FROM public.products p
JOIN public.groups g ON g.id = p.group_id
WHERE p.user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
  AND p.group_id = :group_id
ORDER BY p.name ASC;


-- ----------------------------------------------------------------------------
-- B2. Todos os produtos de uma marca (aqui: fornecedor do produto)
-- ----------------------------------------------------------------------------
SELECT
    p.id,
    p.name,
    s.name AS marca_fornecedor,
    p.sale_price,
    p.stock_quantity,
    p.active
FROM public.products p
JOIN public.people s ON s.id = p.supplier_id
WHERE p.user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
  AND s.category = 'supplier'
  AND p.supplier_id = :supplier_id
ORDER BY p.name ASC;


-- ----------------------------------------------------------------------------
-- B3. Todos os produtos ativos e inativos
-- ----------------------------------------------------------------------------
SELECT
    active,
    COUNT(*) AS total_produtos,
    ROUND(
        COUNT(*) * 100.0 / NULLIF(SUM(COUNT(*)) OVER (), 0),
        2
    ) AS percentual
FROM public.products
WHERE user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
GROUP BY active;


-- ----------------------------------------------------------------------------
-- B4. Produtos com estoque baixo (estoque atual <= estoque mínimo)
-- ----------------------------------------------------------------------------
SELECT
    p.id,
    p.name,
    p.stock_quantity,
    p.min_stock_quantity,
    g.name AS grupo
FROM public.products p
LEFT JOIN public.groups g ON g.id = p.group_id
WHERE p.user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
  AND p.active = true
  AND p.stock_quantity <= p.min_stock_quantity
ORDER BY p.stock_quantity ASC;


-- ============================================================================
-- C. RELATÓRIOS DE VENDAS
-- ============================================================================

-- ----------------------------------------------------------------------------
-- C1. Todas as vendas de um período, ordenadas por valor e/ou data
-- ----------------------------------------------------------------------------
SELECT
    s.id,
    s.code,
    p.name AS cliente,
    s.total,
    s.status,
    s.payment_method,
    s.created_at
FROM public.sales s
LEFT JOIN public.people p ON p.id = s.person_id
WHERE s.user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
  AND s.created_at BETWEEN :data_inicio AND :data_fim
ORDER BY s.total DESC, s.created_at DESC;


-- ----------------------------------------------------------------------------
-- C2. Vendas agrupadas por cliente
-- ----------------------------------------------------------------------------
SELECT
    COALESCE(p.name, 'Cliente avulso') AS cliente,
    COUNT(s.id) AS total_vendas,
    SUM(s.total) AS valor_total
FROM public.sales s
LEFT JOIN public.people p ON p.id = s.person_id
WHERE s.user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
  AND s.status = 'completed'
GROUP BY COALESCE(p.name, 'Cliente avulso')
ORDER BY valor_total DESC;


-- ----------------------------------------------------------------------------
-- C3. Produtos que foram vendidos por período
-- ----------------------------------------------------------------------------
SELECT
    pr.id,
    pr.name AS produto,
    SUM(si.quantity) AS quantidade_vendida,
    SUM(si.total_price) AS valor_total
FROM public.sale_items si
JOIN public.sales s ON s.id = si.sale_id
JOIN public.products pr ON pr.id = si.product_id
WHERE s.user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
  AND s.status = 'completed'
  AND s.created_at BETWEEN :data_inicio AND :data_fim
GROUP BY pr.id, pr.name
ORDER BY quantidade_vendida DESC;


-- ----------------------------------------------------------------------------
-- C4. Grupos e marcas (fornecedores) vendidos no período
-- ----------------------------------------------------------------------------
SELECT
    COALESCE(g.name, 'Sem grupo') AS grupo,
    COALESCE(f.name, 'Sem fornecedor') AS marca_fornecedor,
    SUM(si.quantity) AS quantidade_vendida,
    SUM(si.total_price) AS valor_total
FROM public.sale_items si
JOIN public.sales s ON s.id = si.sale_id
JOIN public.products pr ON pr.id = si.product_id
LEFT JOIN public.groups g ON g.id = pr.group_id
LEFT JOIN public.people f ON f.id = pr.supplier_id
WHERE s.user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
  AND s.status = 'completed'
  AND s.created_at BETWEEN :data_inicio AND :data_fim
GROUP BY COALESCE(g.name, 'Sem grupo'), COALESCE(f.name, 'Sem fornecedor')
ORDER BY valor_total DESC;


-- ----------------------------------------------------------------------------
-- C5. Relação de produtos sem vendas (ativos que nunca venderam)
-- ----------------------------------------------------------------------------
SELECT
    pr.id,
    pr.name,
    pr.stock_quantity
FROM public.products pr
WHERE pr.user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
  AND pr.active = true
  AND NOT EXISTS (
      SELECT 1
      FROM public.sale_items si
      JOIN public.sales s ON s.id = si.sale_id
      WHERE si.product_id = pr.id
        AND s.user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
        AND s.status = 'completed'
  )
ORDER BY pr.name ASC;


-- ----------------------------------------------------------------------------
-- C6. Média de tempo (em dias) entre vendas de cada produto
-- ----------------------------------------------------------------------------
WITH vendas_por_produto AS (
    SELECT
        si.product_id,
        s.created_at,
        LAG(s.created_at) OVER (
            PARTITION BY si.product_id ORDER BY s.created_at
        ) AS venda_anterior
    FROM public.sale_items si
    JOIN public.sales s ON s.id = si.sale_id
    WHERE s.user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
      AND s.status = 'completed'
)
SELECT
    pr.id,
    pr.name AS produto,
    ROUND(
        AVG(EXTRACT(EPOCH FROM (v.created_at - v.venda_anterior)) / 86400.0),
        1
    ) AS media_dias_entre_vendas
FROM vendas_por_produto v
JOIN public.products pr ON pr.id = v.product_id
WHERE v.venda_anterior IS NOT NULL
GROUP BY pr.id, pr.name
ORDER BY media_dias_entre_vendas ASC;


-- ----------------------------------------------------------------------------
-- C7. Vendas por dia / mês / ano
-- ----------------------------------------------------------------------------
-- Por dia
SELECT
    DATE(created_at) AS dia,
    COUNT(*) AS total_vendas,
    SUM(total) AS valor_total
FROM public.sales
WHERE user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
  AND status = 'completed'
GROUP BY DATE(created_at)
ORDER BY dia ASC;

-- Por mês
SELECT
    TO_CHAR(created_at, 'YYYY-MM') AS mes,
    COUNT(*) AS total_vendas,
    SUM(total) AS valor_total
FROM public.sales
WHERE user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
  AND status = 'completed'
GROUP BY TO_CHAR(created_at, 'YYYY-MM')
ORDER BY mes ASC;

-- Por ano
SELECT
    EXTRACT(YEAR FROM created_at) AS ano,
    COUNT(*) AS total_vendas,
    SUM(total) AS valor_total
FROM public.sales
WHERE user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
  AND status = 'completed'
GROUP BY EXTRACT(YEAR FROM created_at)
ORDER BY ano ASC;


-- ============================================================================
-- D. RELATÓRIOS DE COMPRAS
-- ============================================================================

-- ----------------------------------------------------------------------------
-- D1. Todas as compras de um período
-- ----------------------------------------------------------------------------
SELECT
    pu.id,
    pu.code,
    s.name AS fornecedor,
    pu.total,
    pu.status,
    pu.created_at
FROM public.purchases pu
JOIN public.people s ON s.id = pu.supplier_id
WHERE pu.user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
  AND pu.created_at BETWEEN :data_inicio AND :data_fim
ORDER BY pu.created_at DESC;


-- ----------------------------------------------------------------------------
-- D2. Todas as compras de um fornecedor específico
-- ----------------------------------------------------------------------------
SELECT
    pu.id,
    pu.code,
    pu.total,
    pu.status,
    pu.created_at
FROM public.purchases pu
WHERE pu.user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
  AND pu.supplier_id = :supplier_id
ORDER BY pu.created_at DESC;


-- ----------------------------------------------------------------------------
-- D3. Produtos que têm vendas e estão com estoque baixo
-- ----------------------------------------------------------------------------
SELECT
    pr.id,
    pr.name,
    pr.stock_quantity,
    pr.min_stock_quantity,
    COUNT(si.id) AS total_vendas_historico
FROM public.products pr
JOIN public.sale_items si ON si.product_id = pr.id
JOIN public.sales s ON s.id = si.sale_id AND s.status = 'completed'
WHERE pr.user_id = '01a0160c-640c-70ff-b265-76af4b0fca63'
  AND pr.active = true
  AND pr.stock_quantity <= pr.min_stock_quantity
GROUP BY pr.id, pr.name, pr.stock_quantity, pr.min_stock_quantity
ORDER BY pr.stock_quantity ASC;