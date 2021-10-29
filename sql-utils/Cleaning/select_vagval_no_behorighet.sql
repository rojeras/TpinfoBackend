-- Leta fram vägval där det inte finns något motsvarande anropsbehörighet i en TP

SELECT DISTINCT
                CONCAT(plattform.name, ' ', plattform.environment) AS plattform,
                contract.namespace AS namespace,
                la.value AS logicalAddress
FROM
     TakRouting rout,
     TakPlattform plattform,
     TakServiceContract contract,
     TakLogicalAddress la
WHERE rout.plattformId = plattform.id
  AND rout.serviceContractId = contract.id
  AND rout.logicalAddressId = la.id
  AND '2019-01-17' BETWEEN rout.dateEffective AND rout.dateEnd
  AND rout.logicalAddressId NOT IN (
  SELECT DISTINCT auth.logicalAddressId
  FROM TakCallAuthorization auth
  WHERE rout.serviceContractId = auth.serviceContractId
    AND rout.plattformId = auth.plattformId
)
ORDER BY plattform, la.id, contract.id
;
