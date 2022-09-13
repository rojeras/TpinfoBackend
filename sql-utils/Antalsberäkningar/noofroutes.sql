select distinct
    tr.serviceContractId as 'Contract id',
    tr.serviceComponentId as 'Producer id'
    -- ti.logicalAddressId as 'Logical address id'
from
     TakRouting tr
where
        tr.plattformId = 3
  and dateEnd >= '2016-03-20'
  and dateEffective <= '2016-03-20'
;