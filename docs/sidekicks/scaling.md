# Sidekick — Fórmula de Scaling de Stats

## Fórmula actual (implementada em `Character::sidekickScaledBases`)

```
sum_bases = heroLevel × multiplicador_qualidade

stat_base_stamina         = round(sum_bases × 0.28)
stat_base_strength        = round(sum_bases × 0.43)
stat_base_critical_rating = round(sum_bases × 0.18)
stat_base_dodge_rating    = round(sum_bases × 0.11)
```

| Qualidade | Multiplicador | Factor de crescimento (statAtLevel) |
|---|---|---|
| Comum (1) | `heroLevel × 1.2` | 0.010 por nível |
| Épico (3) | `heroLevel × 2.5` | 0.017 por nível |
| Lendário (4) | `heroLevel × 4.2` | 0.025 por nível |

**Variação aleatória na geração da oferta/prémio:** ±15% por atributo
(bases entre `mid×0.85` e `mid×1.15`, arredondadas e com mínimo 1).
No brechó, os valores são guardados no item de tipo 9 e mostrados no preview.
A compra copia-os para `stat_base_*`, sem novo sorteio nem atualização pelo
nível atual do herói. Só ofertas antigas com quatro atributos a zero usam
a fórmula no momento da compra.

### Fórmula de stat ao nível N do sidekick

```
stat_no_nivel(base, level, factor) = round(base + base × factor × (level − 1))
```

### Ratio entre qualidades ao mesmo nível de herói (lv30 sidekick)

- Épico / Comum ≈ **2.4×**
- Lendário / Épico ≈ **1.9×**
- Lendário / Comum ≈ **4.7×**

---

## Tabela de referência — Stats totais (sum 4 stats) por nível de herói

> Colunas: **lv1** = sidekick ao nível 1 (recém comprado), **lv30** = sidekick ao nível máximo.
> A variação ±15% não está incluída (são os midpoints).

| Herói lv | Comum lv1 | Comum lv30 | Épico lv1 | Épico lv30 | Lendário lv1 | Lendário lv30 |
|---:|---:|---:|---:|---:|---:|---:|
| 60 | 72 | 93 | 151 | 225 | 252 | 434 |
| 110 | 133 | 172 | 275 | 411 | 462 | 797 |
| 160 | 193 | 249 | 400 | 597 | 672 | 1160 |
| 210 | 252 | 325 | 526 | 785 | 882 | 1521 |
| 260 | 311 | 401 | 651 | 972 | 1093 | 1886 |
| 310 | 372 | 479 | 775 | 1157 | 1302 | 2247 |
| 360 | 433 | 559 | 900 | 1344 | 1511 | 2606 |
| 410 | 493 | 636 | 1026 | 1531 | 1721 | 2969 |
| 460 | 552 | 713 | 1151 | 1719 | 1933 | 3333 |
| 510 | 611 | 788 | 1275 | 1903 | 2143 | 3697 |
| 560 | 672 | 867 | 1400 | 2090 | 2352 | 4058 |
| 610 | 733 | 944 | 1526 | 2279 | 2562 | 4419 |
| 660 | 793 | 1022 | 1651 | 2465 | 2772 | 4782 |
| 710 | 852 | 1098 | 1775 | 2650 | 2982 | 5143 |
| 760 | 911 | 1176 | 1900 | 2837 | 3193 | 5507 |
| 810 | 972 | 1254 | 2026 | 3025 | 3402 | 5869 |
| 860 | 1033 | 1333 | 2151 | 3212 | 3611 | 6229 |
| 910 | 1093 | 1410 | 2275 | 3396 | 3821 | 6592 |
| 960 | 1152 | 1487 | 2400 | 3583 | 4033 | 6957 |
| 1010 | 1211 | 1562 | 2526 | 3771 | 4243 | 7319 |
| 1060 | 1272 | 1641 | 2651 | 3958 | 4452 | 7680 |
| 1110 | 1333 | 1720 | 2775 | 4143 | 4662 | 8042 |
| 1160 | 1393 | 1797 | 2900 | 4329 | 4872 | 8405 |
| 1210 | 1452 | 1873 | 3026 | 4518 | 5082 | 8766 |
| 1260 | 1511 | 1950 | 3151 | 4705 | 5293 | 9130 |
| 1310 | 1572 | 2028 | 3275 | 4889 | 5502 | 9491 |
| 1360 | 1633 | 2107 | 3400 | 5076 | 5711 | 9851 |
| 1410 | 1693 | 2183 | 3526 | 5264 | 5921 | 10214 |
| 1460 | 1752 | 2259 | 3651 | 5451 | 6133 | 10579 |
| 1510 | 1811 | 2337 | 3775 | 5636 | 6343 | 10942 |
| 1560 | 1872 | 2415 | 3900 | 5822 | 6552 | 11302 |
| 1610 | 1933 | 2494 | 4026 | 6010 | 6762 | 11663 |
| 1660 | 1993 | 2572 | 4151 | 6197 | 6972 | 12027 |
| 1710 | 2052 | 2648 | 4275 | 6383 | 7182 | 12389 |
| 1760 | 2111 | 2722 | 4400 | 6569 | 7393 | 12753 |

---

## Notas

- **Scaling linear e contínuo** desde o nível 60 (desbloqueio dos sidekicks) sem floor.
- Crescimento por 10 níveis de herói (sidekick épico lv30): **+37 stats** aprox.
- Estes são os **stats próprios** do sidekick — o bónus das skills passivas (que multiplica as stats base do herói) é separado e tende a dominar em níveis altos.
- Ficheiro: `server-laravel/app/HeroZero/Character.php` → `sidekickScaledBases()`

## Prémios de vilão

`Character::randomSidekickBases()` partilha o scaling e a variação de ±15%
entre a compra na loja e os prémios de vilão. Para o vilão, o sorteio acontece
na preparação do prémio; as quatro bases são guardadas nos campos `stat_*` do
preview de tipo 9 e copiadas para `stat_base_*` no resgate. O pet entregue tem
as mesmas bases anunciadas, mesmo que o herói suba de nível entretanto.

O pool de vilão continua a excluir pets lendários e ligados ao passe. Previews
antigos com quatro atributos a zero usam o scaling atual no momento do resgate;
previews com atributos já preenchidos preservam os valores persistidos.

## Oferta e compra no brechó (2026-10-05)

O antigo `sidekickEstimatedStats` mostrava apenas os midpoints da fórmula,
iguais para pets da mesma qualidade e nível do herói. Foi substituído por
`randomSidekickItemStats`, que sorteia uma vez e guarda os valores em `items`.
A compra preserva esses atributos, mesmo depois de o herói subir de nível.
Ofertas já existentes com atributos preenchidos mantêm os valores anunciados;
os próximos refreshes passam a variar os valores mostrados. O sorteio das
habilidades genéricas e as habilidades fixas continuam a seguir as suas regras.

Validação: `test_shop_pet_stats.php --hero="skel"` exercita geração, compra real,
igualdade preview/bases/atributos ao nível 1, mudança de nível antes da compra,
repetição rejeitada e compatibilidade de ofertas antigas a zero, com rollback.
