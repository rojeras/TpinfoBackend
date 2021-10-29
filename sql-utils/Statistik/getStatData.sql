SELECT
    sdt.date AS Date,
    compConsumer.value AS 'Consumer HSA-id',
    compConsumer.description AS 'Consumer name',
    contract.contractName AS 'Service contract',
    la.value AS 'Logical address id',
    la.description AS 'Loccal address name',
    compProducer.value AS 'Producer HSA-id',
    compProducer.description AS 'Producer name',
    sdt.calls AS 'Number of calls'
FROM
     StatDataTable sdt,
     TakServiceComponent compConsumer,
     TakServiceComponent compProducer,
     TakServiceContract contract,
     TakLogicalAddress la
WHERE
        sdt.consumerId = compConsumer.id
    AND sdt.contractId = contract.id
    AND sdt.logicalAddressId = la.id
    AND sdt.producerId = compProducer.id
    AND sdt.plattformId = 3 -- SLL-PROD
    AND compConsumer.value = 'SE5567766992-CC2' -- Agilit Synk
    -- AND contract.contractName = 'MakeBooking'
ORDER BY
    Date, `Consumer HSA-id`, `Service contract`
;