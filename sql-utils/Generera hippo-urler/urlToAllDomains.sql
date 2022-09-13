SELECT
    domain.domainName,
    CONCAT('https://integrationer.tjansteplattform.se/hippo/?filter=d',domain.id)
FROM
    TakServiceDomain domain