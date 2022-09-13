SELECT
    CONCAT(DATE_FORMAT(sdt.date, '%M') , ' ', YEAR(sdt.date )) AS Månad,
    sum(sdt.calls) AS "Antal anrop"
FROM
    StatDataTable sdt,
    TakServiceComponent compConsumer,
    TakServiceContract contract
WHERE
      sdt.consumerId = compConsumer.id
  AND sdt.contractId = contract.id
  AND sdt.plattformId = 3 -- SLL-PROD
 AND compConsumer.value = 'SE2321000016-92V4' -- 1177 Vårdguidens E-tjänster
 AND contract.contractName = 'GetSubjectOfCareSchedule'
--  AND compConsumer.value = 'SE5565594230-BDK' -- Journalen
--  AND contract.contractName = 'GetCareDocumentation'
  AND sdt.date >= '2019-01-01'
-- ORDER BY Date
GROUP BY YEAR(date), MONTH(date)
;

-- Journalen
-- AND compConsumer.value = 'SE5565594230-BDK' -- Journalen
-- AND contract.contractName = 'GetCareDocumentation'

--
-- AND compConsumer.value = 'SE2321000016-92V4' -- 1177 Vårdguidens E-tjänster
-- AND contract.contractName = 'GetSubjectOfCareSchedule'
