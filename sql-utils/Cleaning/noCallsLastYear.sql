SELECT DISTINCT
    -- sdt1.date as date1,
    -- sdt1.consumerId as consumerId,
    comp.value as 'HSA-id',
    comp.description
    -- sdt1.contractId as contractId,
    -- sdt1.contractId as logicalAddressId,
    -- sdt1.calls as calls,
    -- sdt1.producerId as serviceProducer
FROM
    StatDataTable sdt1,
    TakServiceComponent comp
WHERE
        sdt1.date < '2019-09-30'
  AND sdt1.plattformId = 3
  AND sdt1.consumerId = comp.id
  AND sdt1.consumerId NOT IN (
    SELECT consumerId
    FROM StatDataTable sdt2
    WHERE sdt2.date > '2019-09-30'
)
-- ORDER BY date1
;