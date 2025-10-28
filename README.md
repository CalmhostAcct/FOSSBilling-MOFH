# FOSSBilling-MOFH

**Unofficial MyOwnFreeHost (MOFH) Server Manager for FOSSBilling (Untested)**

This unofficial module adds full integration between [FOSSBilling](https://www.fossbilling.org) and [MyOwnFreeHost](https://www.myownfreehost.net), allowing you to automatically create, suspend, and manage free hosting accounts through your MOFH reseller API.

---

## 📦 Features

- ✅ Create, suspend, unsuspend, and terminate hosting accounts  
- ✅ Change account passwords and packages  
- ✅ Retrieve available packages (via JSON API)  
- ✅ Uses `/xml-api/$function` endpoints for all operations  
- ✅ Uses `/json-api/listpkgs` for listing packages (since XML version is broken)  
- ✅ Dynamic cPanel login URLs (`https://cpanel.<reseller-domain>`)  
- ✅ Configurable reseller domain, API username, and key  
- ✅ Full cURL error handling and logging  
- 🧩 Licensed under **Apache 2.0**

---

## ⚙️ Installation

1. **Download the file**  
   Get the latest [`MOFH.php`](./MOFH.php) file from this repository.

2. **Copy it into FOSSBilling**  
   Place the file inside your FOSSBilling installation at:

```

/library/Server/Manager/MOFH.php

```

3. **Add the Server in FOSSBilling Admin Panel**
- Go to **Settings → Servers → Add new server**
- Choose **MyOwnFreeHost (MOFH)** from the server type list
- Enter your:
  - **API Username**
  - **API Password**
  - **Reseller Domain** (example: `myreseller.com`)

4. **Test the connection**
- Click **Test Connection**  
- If successful, you’ll see “MOFH connection successful”

---

## 🧠 Usage Notes

- The module uses the **MOFH XML API** for all major functions:
- `/xml-api/version`
- `/xml-api/createacct`
- `/xml-api/suspendacct`
- `/xml-api/unsuspendacct`
- `/xml-api/removeacct`
- `/xml-api/changepackage`
- `/xml-api/passwd`

- The **`listpkgs`** function uses the **JSON API** endpoint:
```

[https://panel.myownfreehost.net/json-api/listpkgs](https://panel.myownfreehost.net/json-api/listpkgs)

```

- Unsupported operations such as IP or username changes will return standardized FOSSBilling `Server_Exception` messages.

---

## 🖥️ Login URLs

| Type | URL Pattern |
|------|--------------|
| cPanel (per client) | `https://cpanel.<reseller-domain>` |
| Reseller Panel | `https://panel.myownfreehost.net/` |

Example:  
If your reseller domain is `examplehost.com`, the client login will be  
👉 `https://cpanel.examplehost.com`

---

## 🧾 License

```

Copyright 2022-2025 FOSSBilling-MOFH
Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

```
http://www.apache.org/licenses/LICENSE-2.0
```

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

```

---

## 🤝 Contributing

Pull requests, feature suggestions, and improvements are welcome!  
Just fork this repository, make your changes, and submit a PR.

---

## 🧩 Credits

- Built for [FOSSBilling](https://www.fossbilling.org)
- Based on the MyOwnFreeHost Reseller API
- Licensed under the **Apache 2.0** license
