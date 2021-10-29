-- Listar alla tjänstekonsumenter som är anslutna "idag" och där beskrivningen innehåller del av ANNULERAD

SELECT DISTINCT
  sc.value,
  sc.description,
  tp.name,
  tp.environment
FROM
  TakServiceComponent sc,
  TakIntegration ti,
  TakPlattform tp
WHERE
     ((  ti.consumerId = sc.id AND ti.firstPlattformId = tp.id )
     OR ( ti.producerid = sc.id AND ti.lastPlattformId = tp.id ) )
  AND ti.dateEnd = '2019-01-17' -- Today
  AND description LIKE '%ANNU%';