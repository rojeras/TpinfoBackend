SELECT DISTINCT -- sdt1.date,
                sc1.value        AS ConsumerHsa,
                sc1.description  AS ConsumerDescription
                -- con.namespace AS Contract,
                -- la.value         AS LogcialAddress,
                -- la.description   AS LogicalAddressDescription,
                -- sc2.value        AS ProducerHsa,
                -- sc2.description  AS ProducerDescription
FROM StatDataTable sdt1,
     TakServiceComponent sc1,
     TakServiceComponent sc2,
     TakServiceContract con,
     TakLogicalAddress la
WHERE sdt1.plattformId = 4
  AND sdt1.date < '2019-01-01'
  AND
  -- sdt1.date not in (
    NOT EXISTS(
            SELECT sdt2.date
            FROM StatDataTable sdt2
            WHERE sdt2.consumerId = sdt1.consumerId
              AND sdt2.contractId = sdt1.contractId
              AND sdt2.logicalAddressId = sdt1.logicalAddressId
              AND sdt2.producerId = sdt1.producerId
              AND sdt2.date > '2019-01-01'
        )
  AND sdt1.consumerId = sc1.id
  AND sdt1.producerId = sc2.id
  AND sdt1.contractId = con.id
  AND sdt1.logicalAddressId = la.id
ORDER BY sdt1.consumerId,
         sdt1.contractId,
         sdt1.logicalAddressId,
         sdt1.date DESC
;