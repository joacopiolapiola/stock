CREATE DATABASE IF NOT EXISTS viernes;
USE viernes;

CREATE TABLE productos(
    id INT(9) PRIMARY KEY,
    precio INT(9),
    nombre VARCHAR(20)
);

CREATE TABLE reporte(
    id INT(9) PRIMARY KEY,
    producto INT(9),
    dia TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    entrada INT(9),
    salida INT(9),
    existencia INT(9),
    FOREIGN KEY (producto)
        REFERENCES productos(id)
);

CREATE TABLE vars(
    ganancia FLOAT(5,2)
);
a
