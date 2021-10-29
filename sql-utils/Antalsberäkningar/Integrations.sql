select distinct
    ti.consumerId as 'Consumer id',
    ti.contractId as 'Contract id',
    ti.producerid as 'Producer id'
    -- ti.logicalAddressId as 'Logical address id'
from TakIntegration ti
where
        ti.lastPlattformId = 5
  and dateEnd >= '2020-10-28'
  and dateEffective <= '2020-10-28'
;