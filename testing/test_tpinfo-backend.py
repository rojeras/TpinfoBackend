# To use this test suite
#   1. Install python3
#   2. Install pip3
#   3. pip install pytest
#   4. pip install requests
#   5. Edit the "BACKEND_SERVER" variable below to point to the server to be tested
#   6. Execute command "pytest" in the directory containing this file

import requests

# Edit the following line
BACKEND_SERVER = "qa.integrationer.tjansteplattform.se"
# BACKEND_SERVER = "localhost:5555"


BASE_URL = f"http://{BACKEND_SERVER}/tpdb/tpdbapi.php/api/v1"

def test_reset_cache():
    # Do not reset the cache of the production or QA systems
    if (not BACKEND_SERVER.endswith("integrationer.tjansteplattform.se")):
        response = requests.get(f"{BASE_URL}/reset")
        assert response.status_code == 200


def test_dates():
    response = requests.get(f"{BASE_URL}/dates")
    assert response.status_code == 200
    assert response.headers["Content-Type"] == "application/json"

    response_body = response.json()
    assert len(response_body) == 1

    response_body_integrations_dates = response_body["dates"]["integrations"]
    assert len(response_body_integrations_dates) > 1000

    response_body_statistics_dates = response_body["dates"]["statistics"]
    assert len(response_body_statistics_dates) > 1000

    response_body_statistics_first_date = response_body["dates"]["statistics"][0]
    assert response_body_statistics_first_date == "2017-07-30"


def test_domains():
    response = requests.get(f"{BASE_URL}/domains")
    assert response.status_code == 200
    assert response.headers["Content-Type"] == "application/json"

    response_body = response.json()
    assert len(response_body) > 50

    response_body_first = response_body[0]
    assert response_body_first["domainName"] == "crm:carelisting"


def test_contracts():
    response = requests.get(f"{BASE_URL}/contracts")
    assert response.status_code == 200
    assert response.headers["Content-Type"] == "application/json"

    response_body = response.json()
    assert len(response_body) > 300

    response_body_first = response_body[0]
    assert response_body_first["name"] == "GetListing"


def test_components():
    response = requests.get(f"{BASE_URL}/components")
    assert response.status_code == 200
    assert response.headers["Content-Type"] == "application/json"

    response_body = response.json()
    assert len(response_body) > 1000

    response_body_first = response_body[0]
    assert response_body_first["hsaId"] == "SE2321000016-A12C"


def test_logicalAddress():
    response = requests.get(f"{BASE_URL}/logicalAddress")
    assert response.status_code == 200
    assert response.headers["Content-Type"] == "application/json"

    response_body = response.json()
    assert len(response_body) > 10000

    response_body_first = response_body[0]
    assert response_body_first["logicalAddress"] == "01"


def test_plattforms():
    response = requests.get(f"{BASE_URL}/plattforms")
    assert response.status_code == 200
    assert response.headers["Content-Type"] == "application/json"

    response_body = response.json()
    assert len(response_body) > 3

    response_body_first = response_body[0]
    assert response_body_first["id"] == 2


def test_plattformChains():
    response = requests.get(f"{BASE_URL}/plattformChains")
    assert response.status_code == 200
    assert response.headers["Content-Type"] == "application/json"

    response_body = response.json()
    assert len(response_body) > 10

    response_body_first = response_body[0]
    assert response_body_first["id"] == 0


def test_statPlattforms():
    response = requests.get(f"{BASE_URL}/statPlattforms")
    assert response.status_code == 200
    assert response.headers["Content-Type"] == "application/json"

    response_body = response.json()
    assert len(response_body) == 2

    response_body_first = response_body[0]
    assert response_body_first["id"] == 3

def test_integrations():
    response = requests.get(f"{BASE_URL}/integrations?dummy&dateEffective=2021-07-01&dateEnd=2021-07-01&domainId=8&firstPlattformId=5&lastPlattformId=3")
    assert response.status_code == 200
    assert response.headers["Content-Type"] == "application/json"

    response_body = response.json()
    assert len(response_body) == 3

    response_body_integrations = response_body["integrations"]
    assert len(response_body_integrations) == 6

    print(response_body_integrations)

    # Verify the contract in the integration
    response_body_integrations_first = response_body["integrations"][0]
    assert response_body_integrations_first[6] == 8

def test_statistics():
    response = requests.get(f"{BASE_URL}/statistics?dummy&dateEffective=2021-07-01&dateEnd=2021-07-31&producerId=476&firstPlattformId=3&lastPlattformId=3")
    assert response.status_code == 200
    assert response.headers["Content-Type"] == "application/json"

    response_body = response.json()
    assert len(response_body) == 62

    assert response_body[0][6] == 104158
    # response_body_first = response_body[0]
    # assert response_body_first["id"] == 3

def test_history():
    response = requests.get(f"{BASE_URL}/history?dummy&dateEffective=2021-07-01&dateEnd=2021-07-31&producerId=476&firstPlattformId=3&lastPlattformId=3")
    assert response.status_code == 200
    assert response.headers["Content-Type"] == "application/json"

    response_body = response.json()
    response_body_history = response_body["history"]
    assert len(response_body_history) == 15
    assert response_body_history["2021-07-05"] == 246240
