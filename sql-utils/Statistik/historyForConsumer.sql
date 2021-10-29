SELECT
    CONCAT(YEAR(sdt.date ), ' ', DATE_FORMAT(sdt.date, '%M')) AS Month,
    sdt.calls AS 'Number of calls'
FROM
    StatDataTable sdt,
    TakServiceComponent compConsumer,
    TakServiceContract contract
WHERE
      sdt.consumerId = compConsumer.id
  AND sdt.contractId = contract.id
  AND sdt.plattformId = 3 -- SLL-PROD
  AND compConsumer.value = 'SE5565594230-BDK' -- Journalen
  AND contract.contractName = 'GetCareDocumentation'
  AND sdt.date >= '2019-01-01'
-- ORDER BY Date
GROUP BY YEAR(date), MONTH(date)
;