/*
I produktion finns 1114 st Lan som har vägval och behörighet inom shceduling,
men endast 1106 st Lan som har vägval och behörighet för Makebooking.

(differens 8st)

Dessa 8 kan dock, vad det verkar, ha behörighet för alla 8 schedling kontrakt hos NTjP.
Så viss diskrepans kan förekomm mellan oss och NTjP.

Vid tillfälle skulle det vara intressant att söka ut dem för att kunna säkerställa om inera eller vi har takat ”fel”, och om dom vill korrigera detta.



Vad jag har märkt är det särskilt makeBooking och getAllTimeTypes som saknas i dessa fall.
Kanske kan man göra en fråga som säger
om en LA har vägval för cancelBooking men inte makebooking. OCH finns i ineras tak och har makeBooking där?

Eller något annat kreativt som kan visa oss vilka dessa 8 är…

Grundinformation:
  MakeBooking id = 117
  CancelBooking id = 114
  NTjP PROD id = 5
  SLL-RPT PROD id = 3
  Current date end i TPDB = 2019-06-12
  Consumer Inera AB -- 1177 Vårdguidens e-tjänster SE2321000016-92V4 = 354
  Producer 	Region Stockholm -- Tjänsteplattform SE2321000016-7P35 = 307
  Consumer Inera AB -- Tjänsteplattform -- Nationella tjänster HSASERVICES-106J = 139
  Domain id crm:scheduling = 11

 */

SELECT DISTINCT la.value,
                la.description
FROM TPDB.TakIntegration inter,
     TakLogicalAddress la
WHERE domainId = 11
  AND lastPlattformId = 5
  AND contractId = 117
  AND producerid = 307
  -- AND consumerId = 354
  AND inter.logicalAddressId = la.id
  AND inter.logicalAddressId NOT IN (
    SELECT inter2.logicalAddressId
    FROM TPDB.TakIntegration inter2
    WHERE domainId = 11
      AND contractId = 117
      AND lastPlattformId = 3
      AND contractId = 117
      AND consumerId = 139
)
;

SELECT count(DISTINCT logicalAddressId)
FROM TPDB.TakIntegration
WHERE domainId = 11
  AND lastPlattformId = 3
  AND contractId = 117
  AND consumerId = 139
-- AND consumerId = 354
;

SELECT DISTINCT la.value,
                la.description
FROM TakLogicalAddress la,
     TakIntegration inter
WHERE
    la.value LIKE '%?'
OR la.value LIKE 'SKALL%'
;