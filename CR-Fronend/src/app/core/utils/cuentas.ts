/**
 * Una cuenta CTS: solo cifras, con guiones si el banco la escribe así
 * (191-12345678-0-12). La misma regla que AltaDeEmpleado::REGLA_CUENTA_CTS.
 */
export const CUENTA_CTS = /^[0-9]+(-[0-9]+)*$/;
